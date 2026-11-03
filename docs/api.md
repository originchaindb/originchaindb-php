# Using the client

Create `db` as shown in the [quickstart](../README.md). These independent examples
assume an existing `demo.items` schema and an appropriate search index.

```php
$result = $db->sql->run('SELECT $1 AS value', [7]);
$row = $db->rows->get('demo.items', 'one');
$vectors = $db->vector->search('demo.items', [1.0, 0.0]);
$text = $db->fts->search('demo.items', 'body', 'hello');
$graph = $db->graph->query('MATCH (n) RETURN $name', ['name' => 'one']);
```

## Results, errors, and lifetime

`SqlResult->rows` contains associative arrays. `rowsAffected` is `?int`;
integers outside PHP's native range are preserved as strings in row data.
Vector hits expose `id`, `score`, `metadata`, and `raw`. Full-text results expose
`hits`, `facets`, and `raw`.

Catch `OriginChainDB\Exception`; inspect `kind`, `status`, and `requestId`.
`partial` contains the SQL result for batch errors.

Use `Client::fromEnv(timeout: 10.0)` for a ten-second request deadline.
Reuse one client per sequential worker; call `$db->close()` when finished.
The runtime requires the cURL and JSON extensions.

See the [shared behavior contract](contract.md) for parameter binding, batch
semantics, defaults, and retry behavior. The [contract consumer](../tests/consumer.php)
exercises the full common API against a local fixture.
