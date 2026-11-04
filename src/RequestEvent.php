<?php

declare(strict_types=1);

namespace OriginChainDB;

/** Local transport metadata. Excludes URLs, schema names, SQL, values, bodies and credentials.
 * A null errorKind means HTTP success; result decoding may fail afterwards. */
final class RequestEvent
{
    public function __construct(
        public readonly string $method,
        public readonly string $operation,
        public readonly float $duration,
        public readonly ?int $status,
        public readonly ?string $requestId,
        public readonly ?string $errorKind,
    ) {
    }
}
