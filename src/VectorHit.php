<?php

declare(strict_types=1);

namespace OriginChainDB;

final class VectorHit
{
    private function __construct(
        public readonly string $id,
        public readonly float $score,
        public readonly mixed $metadata,
        public readonly object $raw
    ) {
    }

    /** @return list<VectorHit> */
    public static function decode(mixed $value): array
    {
        if (!is_array($value)) {
            throw new Exception('protocol');
        }
        return array_map(static function ($item): self {
            if (!$item instanceof \stdClass) {
                throw new Exception('protocol');
            }
            $id = $item->id ?? $item->vec_id ?? null;
            $score = $item->score ?? null;
            if (!is_string($id) || (!is_int($score) && !is_float($score)) || !is_finite((float)$score)) {
                throw new Exception('protocol');
            }
            return new self($id, (float)$score, $item->metadata ?? null, $item);
        }, $value);
    }
}
