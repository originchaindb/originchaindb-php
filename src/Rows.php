<?php

declare(strict_types=1);

namespace OriginChainDB;

final class Rows
{
    public function __construct(private readonly Client $client)
    {
    }
    public function get(string $schema, string $key): mixed
    {
        return $this->client->request('GET', 'rows/' . Client::segment($schema) . '/' . Client::segment($key));
    }
    public function put(string $schema, array $row): mixed
    {
        return $this->client->request('POST', 'rows/' . Client::segment($schema), (object)$row);
    }
    public function putBatch(string $schema, array $rows): mixed
    {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException('Rows must be a list');
        }
        return $this->client->request('POST', 'rows/' . Client::segment($schema) . '/_batch', array_map(fn (array $row) => (object)$row, $rows));
    }
}
