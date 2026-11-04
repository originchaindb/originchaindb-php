<?php

declare(strict_types=1);

namespace OriginChainDB;

/** Complete statement result. Numeric strings and additional fields are retained. */
final class SqlResult
{
    /** @param list<array<string,mixed>> $rows @param list<SqlResult> $results */
    private function __construct(
        public readonly string $kind,
        public readonly array $rows,
        public readonly ?array $columns,
        public readonly ?object $columnTypes,
        public readonly ?int $rowsAffected,
        public readonly ?int $rowsBuffered,
        public readonly array $results,
        public readonly bool $stoppedOnError,
        public readonly object $raw,
    ) {
    }

    public static function decode(mixed $value): self
    {
        if (!$value instanceof \stdClass) {
            throw new Exception('protocol');
        }
        $kind = $value->kind ?? (property_exists($value, 'error') ? 'error' : null);
        $rows = $value->rows ?? [];
        $children = $value->results ?? [];
        $columns = $value->columns ?? $value->returning ?? null;
        $stopped = $value->stopped_on_error ?? false;
        if (!is_string($kind) || !is_array($rows) || !is_array($children) || !is_bool($stopped)) {
            throw new Exception('protocol');
        }
        foreach ($rows as $row) {
            if (!$row instanceof \stdClass) {
                throw new Exception('protocol');
            }
        }
        if ($columns !== null && (!is_array($columns) || array_filter($columns, fn ($c) => !is_string($c)))) {
            throw new Exception('protocol');
        }
        $types = $value->column_types ?? null;
        if ($types !== null && !$types instanceof \stdClass) {
            throw new Exception('protocol');
        }
        $count = $value->rows_affected ?? ($kind === 'insert' ? ($value->inserted ?? null) : ($kind === 'select' ? 0 : null));
        $buffered = $value->rows_buffered ?? null;
        foreach ([$count, $buffered] as $number) {
            if ($number !== null && (!is_int($number) || $number < 0)) {
                throw new Exception('protocol');
            }
        }
        return new self(
            $kind,
            array_map('get_object_vars', $rows),
            $columns,
            $types,
            $count,
            $buffered,
            array_map(self::decode(...), $children),
            $stopped,
            $value
        );
    }

    public function failed(): bool
    {
        if ($this->stoppedOnError || $this->kind === 'error' || property_exists($this->raw, 'error')) {
            return true;
        }
        foreach ($this->results as $result) {
            if ($result->failed()) {
                return true;
            }
        }
        return false;
    }

    /** Map independent row copies using an application model constructor or validator.
     * @template T
     * @param callable(array<string,mixed>): T $decode
     * @return list<T>
     */
    public function mapRows(callable $decode): array
    {
        $mapped = [];
        foreach ($this->rows as $index => $row) {
            try {
                // Copy nested JSON objects too, preserving numeric strings and null.
                $copy = self::copyValue($row);
                $mapped[] = $decode($copy);
            } catch (\Throwable $error) {
                throw new RowDecodeException($index, $error);
            }
        }
        return $mapped;
    }

    private static function copyValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::copyValue(...), $value);
        }
        if ($value instanceof \stdClass) {
            $copy = new \stdClass();
            foreach (get_object_vars($value) as $key => $item) {
                $copy->{$key} = self::copyValue($item);
            }
            return $copy;
        }
        return $value;
    }
}
