<?php

declare(strict_types=1);

namespace OriginChainDB;

final class Fts
{
    public function __construct(private readonly Client $client)
    {
    }
    public function index(string $table, string $field, string $id, string $text): mixed
    {
        return $this->client->request('POST', 'fts/' . Client::segment($table) . '/' . Client::segment($field), ['doc_id' => $id, 'text' => $text]);
    }
    public function search(string $table, string $field, string $query, int $k = 10, string $mode = 'bm25', bool $highlight = false, ?int $fuzzy = null, ?array $facets = null): FtsResult
    {
        if ($k <= 0 || !in_array($mode, ['bm25', 'boolean', 'phrase'], true)) {
            throw new \InvalidArgumentException('Invalid search options');
        }
        $options = ['q' => $query, 'mode' => $mode];
        if ($mode === 'bm25') {
            $options['k'] = $k;
        }
        if ($highlight) {
            $options['highlight'] = 'true';
        }
        if ($fuzzy !== null) {
            $options['fuzzy'] = $fuzzy;
        }
        if ($facets !== null) {
            $options['facets'] = implode(',', $facets);
        }
        return FtsResult::decode($this->client->request('GET', 'fts/' . Client::segment($table) . '/' . Client::segment($field), query: $options));
    }
}
