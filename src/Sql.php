<?php

declare(strict_types=1);

namespace OriginChainDB;

final class Sql
{
    public function __construct(private readonly Client $client)
    {
    }
    public function run(string $statement, array $params = []): SqlResult
    {
        if (trim($statement) === '' || !array_is_list($params)) {
            throw new \InvalidArgumentException('Provide SQL and a positional parameter list');
        }
        $result = SqlResult::decode($this->client->request('POST', 'sql', ['sql' => $statement, 'params' => $params]));
        if ($result->failed()) {
            throw new Exception('batch', 200, partial: $result);
        }
        return $result;
    }
    public function execute(string $statement, array $params = []): SqlResult
    {
        return $this->run($statement, $params);
    }
    public function query(string $statement, array $params = []): SqlResult
    {
        $result = $this->run($statement, $params);
        if ($result->kind !== 'select') {
            throw new Exception('protocol', 200);
        }
        return $result;
    }
}
