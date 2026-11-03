<?php

declare(strict_types=1);

namespace OriginChainDB;

final class Schemas
{
    public function __construct(private readonly Client $client)
    {
    }
    public function list(): mixed
    {
        return $this->client->request('GET', 'schemas');
    }
    public function get(string $name): mixed
    {
        return $this->client->request('GET', 'schemas/' . Client::segment($name));
    }
    public function register(string $toml): mixed
    {
        return $this->client->request('POST', 'schemas', text: $toml);
    }
}
