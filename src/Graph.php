<?php

declare(strict_types=1);

namespace OriginChainDB;

final class Graph
{
    public function __construct(private readonly Client $client)
    {
    }
    public function query(string $cypher, array $params = []): mixed
    {
        return $this->client->request('POST', 'cypher', ['cypher' => $cypher, 'params' => (object)$params]);
    }
    public function neighbors(string $schema, string $key, string $relation): mixed
    {
        return $this->client->request('GET', 'graph/' . Client::segment($schema) . '/neighbors', query: ['pk' => $key, 'rel' => $relation]);
    }
    public function bfs(string $schema, string $key, string $relation, int $maxDepth = 3): mixed
    {
        if ($maxDepth < 1) {
            throw new \InvalidArgumentException('maxDepth must be positive');
        }
        return $this->client->request('GET', 'graph/' . Client::segment($schema) . '/bfs', query: ['pk' => $key, 'rel' => $relation, 'max_depth' => $maxDepth]);
    }
}
