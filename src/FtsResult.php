<?php

declare(strict_types=1);

namespace OriginChainDB;

/** Uniform ranked, boolean and phrase results. Additional fields survive in raw. */
final class FtsResult
{
    /** @param list<object> $hits */
    private function __construct(public readonly array $hits, public readonly mixed $facets, public readonly mixed $raw)
    {
    }

    public static function decode(mixed $value): self
    {
        $items = $value instanceof \stdClass ? ($value->hits ?? null) : $value;
        if (!is_array($items)) {
            throw new Exception('protocol');
        }
        $hits = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $hits[] = (object)['doc_id' => $item, 'score' => 0.0];
                continue;
            }
            if (!$item instanceof \stdClass || !is_string($item->doc_id ?? null) ||
                (isset($item->score) && (!is_numeric($item->score) || !is_finite((float)$item->score)))) {
                throw new Exception('protocol');
            }
            $hits[] = (object)['doc_id' => $item->doc_id, 'score' => (float)($item->score ?? 0),
                'highlights' => $item->highlights ?? null, 'raw' => $item];
        }
        return new self($hits, $value instanceof \stdClass ? ($value->facets ?? null) : null, $value);
    }
}
