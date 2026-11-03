<?php

declare(strict_types=1);

namespace OriginChainDB;

/** Error messages omit response data. Inspect body explicitly when appropriate. */
class Exception extends \RuntimeException
{
    public function __construct(
        public readonly string $kind,
        public readonly ?int $status = null,
        public readonly mixed $body = null,
        public readonly ?string $requestId = null,
        public readonly ?string $retryAfter = null,
        public readonly ?SqlResult $partial = null,
    ) {
        parent::__construct("OriginChainDB {$kind} error" . ($status === null ? '' : " (HTTP {$status})"));
    }
}
