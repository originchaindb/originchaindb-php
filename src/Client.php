<?php

declare(strict_types=1);

namespace OriginChainDB;

/** Reuse one Client per database. Its cURL handle retains pooled connections. */
final class Client
{
    public const VERSION = '1.0.0-rc.1';
    private ?\CurlHandle $handle;
    private readonly string $url;
    private readonly ?\Closure $observer;
    public readonly Sql $sql;
    public readonly Rows $rows;
    public readonly Schemas $schemas;
    public readonly Vector $vector;
    public readonly Fts $fts;
    public readonly Graph $graph;

    /** The token may be a string or a zero-argument provider returning the current token.
     * httpOptions accepts proxy and certificate options only; TLS and redirects stay enforced. */
    public function __construct(
        string $url,
        private readonly string|\Closure $token,
        private readonly float $timeout = 60.0,
        ?callable $onRequest = null,
        private readonly array $httpOptions = []
    ) {
        $parts = parse_url($url);
        $host = trim($parts['host'] ?? '', '[]');
        $loopback = $host === 'localhost' || $host === '::1' ||
            (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && str_starts_with($host, '127.'));
        if ($parts === false || $host === '' || isset($parts['user']) || isset($parts['pass']) ||
            strpbrk($url, '?#\\') !== false || preg_match('/[\x00-\x20\x7f]/', $url) ||
            (($parts['scheme'] ?? '') !== 'https' && !(($parts['scheme'] ?? '') === 'http' && $loopback))) {
            throw new \InvalidArgumentException('Use the HTTPS database URL copied from Connect; HTTP is only allowed on loopback');
        }
        if (is_string($token) && ($token === '' || preg_match('/[^\x21-\x7e]/', $token))) {
            throw new \InvalidArgumentException('Token must be nonempty printable ASCII without spaces');
        }
        if (!is_finite($timeout) || $timeout <= 0 || $timeout > 2147483) {
            throw new \InvalidArgumentException('Invalid timeout');
        }
        $allowed = [CURLOPT_PROXY, CURLOPT_PROXYPORT, CURLOPT_PROXYTYPE, CURLOPT_PROXYUSERNAME,
            CURLOPT_PROXYPASSWORD, CURLOPT_CAINFO, CURLOPT_CAPATH, CURLOPT_SSLCERT, CURLOPT_SSLKEY, CURLOPT_KEYPASSWD];
        foreach (array_keys($httpOptions) as $option) {
            if (!in_array($option, $allowed, true)) {
                throw new \InvalidArgumentException('Unsupported HTTP option; only proxy and certificate configuration is accepted');
            }
        }
        $this->observer = $onRequest === null ? null : \Closure::fromCallable($onRequest);
        $this->url = rtrim($url, '/');
        $this->handle = curl_init();
        if ($this->handle === false) {
            throw new Exception('transport');
        }
        $this->sql = new Sql($this);
        $this->rows = new Rows($this);
        $this->schemas = new Schemas($this);
        $this->vector = new Vector($this);
        $this->fts = new Fts($this);
        $this->graph = new Graph($this);
    }

    public static function fromEnv(float $timeout = 60.0): self
    {
        return new self(getenv('OC_URL') ?: '', getenv('OC_TOKEN') ?: '', $timeout);
    }

    public function close(): void
    {
        $this->handle = null;
    }
    public function __debugInfo(): array
    {
        return ['client' => 'OriginChainDB'];
    }
    public function __destruct()
    {
        $this->close();
    }

    /** @internal Shared transport. Operation namespaces own paths and bodies. */
    public function request(string $method, string $path, mixed $body = null, array $query = [], ?string $text = null): mixed
    {
        $started = hrtime(true);
        $status = null;
        $requestId = null;
        $kind = null;
        try {
            return $this->requestCore($method, $path, $body, $query, $text, $started, $status, $requestId);
        } catch (\Throwable $error) {
            $kind = $error instanceof Exception ? $error->kind : 'client';
            throw $error;
        } finally {
            if ($this->observer !== null) {
                $first = explode('/', $path, 2)[0];
                $operation = in_array($first, ['sql', 'rows', 'schemas', 'vector', 'fts', 'graph', 'cypher', 'usage', 'query-metrics'], true) ? $first : 'other';
                try {
                    ($this->observer)(new RequestEvent($method, $operation, (hrtime(true) - $started) / 1e9, $status, $requestId, $kind));
                } catch (\Throwable) { /* local observation never alters the result */
                }
            }
        }
    }

    private function requestCore(
        string $method,
        string $path,
        mixed $body,
        array $query,
        ?string $text,
        int $started,
        ?int &$status,
        ?string &$requestId
    ): mixed {
        if ($this->handle === null) {
            throw new \LogicException('Client is closed');
        }
        $token = $this->token;
        if ($token instanceof \Closure) {
            try {
                $token = $token();
            } catch (\Throwable) {
                throw new Exception('credential');
            }
            if (!is_string($token) || $token === '' || preg_match('/[^\x21-\x7e]/', $token)) {
                throw new Exception('credential');
            }
        }
        $remaining = $this->timeout - (hrtime(true) - $started) / 1e9;
        if ($remaining <= 0) {
            throw new Exception('timeout');
        }
        $address = $this->url . '/' . $path;
        if ($query !== []) {
            $address .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json',
            'User-Agent: originchaindb-php/' . self::VERSION, 'X-OC-Logical-Request-Id: ' . bin2hex(random_bytes(16))];
        $payload = $text ?? ($body === null ? null : json_encode($body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        if ($payload !== null) {
            $headers[] = 'Content-Type: ' . ($text === null ? 'application/json' : 'text/plain');
        }
        $responseHeaders = [];
        $raw = '';
        $oversized = false;
        curl_reset($this->handle);
        if ($this->httpOptions !== [] && !curl_setopt_array($this->handle, $this->httpOptions)) {
            throw new \InvalidArgumentException('Invalid proxy or certificate configuration');
        }
        // On Windows, OpenSSL-backed cURL otherwise has no default CA bundle.
        // Honor an explicitly configured bundle; use the OS trust store by default.
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA') && !ini_get('curl.cainfo') && !isset($this->httpOptions[CURLOPT_CAINFO])) {
            curl_setopt($this->handle, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }
        curl_setopt_array($this->handle, [CURLOPT_URL => $address, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT_MS => (int)ceil($remaining * 1000), CURLOPT_CONNECTTIMEOUT_MS => (int)min(10000, ceil($remaining * 1000)),
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                if (str_contains($line, ':')) {
                    [$key, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($key))] = trim($value);
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $data) use (&$raw, &$oversized): int {
                if (strlen($raw) + strlen($data) > 33554432) {
                    $oversized = true;
                    return 0;
                }
                $raw .= $data;
                return strlen($data);
            },
        ]);
        if ($payload !== null) {
            curl_setopt($this->handle, CURLOPT_POSTFIELDS, $payload);
        }
        $ok = curl_exec($this->handle);
        $status = (int)curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE);
        $requestId = $responseHeaders['x-oc-request-id'] ?? null;
        if ($status === 0) {
            $status = null;
        }
        if ($ok === false) {
            $code = curl_errno($this->handle);
            throw new Exception($oversized ? 'protocol' : ($code === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'transport'), body: ['curl_code' => $code]);
        }
        $status ??= 0;
        try {
            $value = $raw === '' ? null : json_decode($raw, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $error) {
            if ($status >= 200 && $status < 300 && !str_starts_with($responseHeaders['content-type'] ?? '', 'text/')) {
                throw new Exception('protocol', $status);
            }
            $value = $raw;
        }
        if ($status < 200 || $status >= 300) {
            $kind = match ($status) {
                401 => 'authentication', 403 => 'permission', 429 => 'rate_limit', default => 'http'
            };
            throw new Exception($kind, $status, $value, $responseHeaders['x-oc-request-id'] ?? null, $responseHeaders['retry-after'] ?? null);
        }
        return $value;
    }

    public static function segment(string $name): string
    {
        if ($name === '' || $name === '.' || $name === '..' || preg_match('/[\x00-\x1f\x7f]/', $name)) {
            throw new \InvalidArgumentException('Path name must be nonempty and contain no control characters');
        }
        return rawurlencode($name);
    }
    public function usage(): mixed
    {
        return $this->request('GET', 'usage');
    }
    public function queryMetrics(int $minutes = 60, int $bucketSeconds = 60): mixed
    {
        return $this->request('GET', 'query-metrics', query: ['minutes' => $minutes, 'bucket_seconds' => $bucketSeconds]);
    }
}
