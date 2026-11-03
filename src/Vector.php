<?php

declare(strict_types=1);

namespace OriginChainDB;

final class Vector
{
    public function __construct(private readonly Client $client)
    {
    }
    private function validate(array $vector): void
    {
        if ($vector === [] || !array_is_list($vector)) {
            throw new \InvalidArgumentException('Vector must be a nonempty list');
        }
        foreach ($vector as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value)) {
                throw new \InvalidArgumentException('Vector must contain finite numbers');
            }
        }
    }
    public function put(string $table, string $id, array $embedding, ?array $metadata = null): mixed
    {
        $this->validate($embedding);
        $body = ['id' => $id, 'embedding' => $embedding, 'dim' => count($embedding)];
        if ($metadata !== null) {
            $body['metadata'] = (object)$metadata;
        }
        return $this->client->request('POST', 'vector/' . Client::segment($table) . '/put', $body);
    }
    /** @return list<VectorHit> */
    public function search(string $table, array $query, int $k = 10, string $metric = 'cosine', ?array $filter = null): array
    {
        $this->validate($query);
        if ($k <= 0) {
            throw new \InvalidArgumentException('k must be positive');
        }
        $body = ['query' => $query, 'dim' => count($query), 'k' => $k, 'metric' => $metric];
        if ($filter !== null) {
            $body['filter'] = (object)$filter;
        }
        return VectorHit::decode($this->client->request('POST', 'vector/' . Client::segment($table) . '/topk', $body));
    }
    public function delete(string $table, string $id): mixed
    {
        return $this->client->request('DELETE', 'vector/' . Client::segment($table) . '/' . Client::segment($id));
    }
}
