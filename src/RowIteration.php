<?php

declare(strict_types=1);

namespace OriginChainDB;

/** @internal Bounded iteration shared by the Rows resource. */
trait RowIteration
{
    private static function checkCancelled(?callable $cancelled): void
    {
        if ($cancelled !== null && $cancelled()) {
            throw new Exception('cancelled');
        }
    }

    /** Lazy acknowledged batches; no automatic replay. Cancellation is checked between requests. */
    public function writeBatches(string $schema, iterable $rows, int $batchSize = 1000, int $maxBytes = 1048576, ?callable $cancelled = null): \Generator
    {
        if ($batchSize < 1 || $batchSize > 10000 || $maxBytes < 2 || $maxBytes > 8388608) {
            throw new \InvalidArgumentException('Invalid batch bounds');
        }
        Client::segment($schema);
        self::checkCancelled($cancelled);
        $batch = [];
        $size = 2;
        $offset = 0;
        $flush = function () use (&$batch, &$offset, $schema, $cancelled): BatchResult {
            self::checkCancelled($cancelled);
            try {
                $value = $this->client->request('POST', 'rows/' . Client::segment($schema) . '/_batch', $batch);
                $inserted = $value->inserted ?? null;
                if (!is_int($inserted) || $inserted < 0 || $inserted > count($batch)) {
                    throw new Exception('protocol');
                }
                return new BatchResult($offset, count($batch), $inserted);
            } catch (\Throwable $error) {
                throw new BatchWriteException($offset, count($batch), 'unknown', $error);
            }
        };
        // This wrapper preserves producer failures without relabeling request failures.
        $input = (static function () use ($rows, &$offset, &$batch): \Generator {
            try {
                foreach ($rows as $row) {
                    yield $row;
                }
            } catch (\Throwable $error) {
                throw new BatchWriteException($offset, count($batch), 'not_sent', $error);
            }
        })();
        foreach ($input as $item) {
            self::checkCancelled($cancelled);
            try {
                if (!is_array($item) && !$item instanceof \stdClass) {
                    throw new \InvalidArgumentException('Each row must be an object');
                }
                $text = json_encode((object)$item, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
                $row = json_decode($text, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
                if (strlen($text) + 2 > $maxBytes) {
                    throw new \InvalidArgumentException('Row exceeds maxBytes');
                }
            } catch (\Throwable $error) {
                throw new BatchWriteException($offset, count($batch) + 1, 'not_sent', $error);
            }
            if ($batch !== [] && $size + 1 + strlen($text) > $maxBytes) {
                $result = $flush();
                $offset += count($batch);
                $batch = [];
                $size = 2;
                yield $result;
            }
            $size += strlen($text) + ($batch === [] ? 0 : 1);
            $batch[] = $row;
            if (count($batch) === $batchSize) {
                $result = $flush();
                $offset += count($batch);
                $batch = [];
                $size = 2;
                yield $result;
            }
            self::checkCancelled($cancelled);
        }
        if ($batch !== []) {
            yield $flush();
        }
    }

    /** Keyset pages over a unique immutable non-null string column, in binary order. */
    public function pages(string $schema, string $key, int $pageSize = 1000, ?string $after = null, ?callable $cancelled = null): \Generator
    {
        $parts = explode('.', $schema);
        if (count($parts) > 2 || $pageSize < 1 || $pageSize > 10000) {
            throw new \InvalidArgumentException('Invalid page options');
        }
        foreach ([...$parts, $key] as $name) {
            if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $name)) {
                throw new \InvalidArgumentException('Use simple schema and key identifiers');
            }
        }
        $table = implode('.', array_map(static fn (string $part): string => '"' . $part . '"', $parts));
        while (true) {
            self::checkCancelled($cancelled);
            $sql = 'SELECT * FROM ' . $table . ($after === null ? '' : ' WHERE "' . $key . '" > $1') . ' ORDER BY "' . $key . '" ASC LIMIT ' . $pageSize;
            $rows = $this->client->sql->query($sql, $after === null ? [] : [$after])->rows;
            if (count($rows) > $pageSize) {
                throw new Exception('protocol');
            }
            $last = $after;
            foreach ($rows as $row) {
                $value = $row[$key] ?? null;
                if (!is_string($value) || ($last !== null && strcmp($value, $last) <= 0)) {
                    throw new Exception('protocol');
                }
                $last = $value;
            }
            if ($rows === []) {
                return;
            }
            $after = count($rows) < $pageSize ? null : $last;
            yield new RowPage($rows, $after);
            if ($after === null) {
                return;
            }
        }
    }

    /** Flatten pages without prefetching; stop consuming to stop requests. */
    public function scan(string $schema, string $key, int $pageSize = 1000, ?string $after = null, ?callable $cancelled = null): \Generator
    {
        foreach ($this->pages($schema, $key, $pageSize, $after, $cancelled) as $page) {
            foreach ($page->rows as $row) {
                self::checkCancelled($cancelled);
                yield $row;
            }
        }
    }
}
