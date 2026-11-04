<?php

declare(strict_types=1);

namespace OriginChainDB;

/** A keyset page. A null nextCursor marks completion. */
final class RowPage
{
    public function __construct(public readonly array $rows, public readonly ?string $nextCursor)
    {
    }
}
