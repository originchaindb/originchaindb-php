<?php

declare(strict_types=1);

namespace OriginChainDB;

/** An unconfirmed range. Inspect unknown outcomes before retrying. */
final class BatchWriteException extends \RuntimeException
{
    public function __construct(public readonly int $offset, public readonly int $count, public readonly string $outcome, \Throwable $previous)
    {
        parent::__construct("Batch at input offset {$offset} failed ({$outcome})", 0, $previous);
    }
}
