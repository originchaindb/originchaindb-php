<?php

declare(strict_types=1);

namespace OriginChainDB;

/** Conversion failure at a zero-based row. The previous exception is available explicitly. */
final class RowDecodeException extends \RuntimeException
{
    public function __construct(public readonly int $row, \Throwable $previous)
    {
        parent::__construct('Cannot decode SQL row ' . $row, 0, $previous);
    }
}
