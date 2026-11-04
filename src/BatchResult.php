<?php

declare(strict_types=1);

namespace OriginChainDB;

/** One acknowledged input range. */
final class BatchResult
{
    public function __construct(public readonly int $offset, public readonly int $count, public readonly int $inserted)
    {
    }
}
