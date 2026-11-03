# OriginChainDB for PHP

The official PHP SDK for working with OriginChainDB through SQL, rows,
vector search, full-text search, and graph relationships. Use one database client
for these operations, with native PHP types and conventions.

[![CI](https://github.com/originchaindb/originchaindb-php/actions/workflows/ci.yml/badge.svg)](https://github.com/originchaindb/originchaindb-php/actions/workflows/ci.yml)

**Requirements:** PHP 8.2+ with cURL and JSON extensions; Composer for installation. **Package:** `originchain/originchaindb`.

> This README describes the **1.0.0-rc.1 source candidate**. It has not been
> published to package registries. Follow the source installation below; an older
> registry package may expose a different API.

[Install](#installation) | [Connect](#connect-and-run-your-first-query) |
[Examples](#product-catalog-example) | [Results](#understanding-sql-results) |
[Errors](#handling-errors) | [Source](#source-layout)

## Installation

Prepare the SDK checkout:

```sh
git clone https://github.com/originchaindb/originchaindb-php.git
cd originchaindb-php
composer install
```

To use it from another application, run these commands in that application's
directory, replacing the path with your checkout:

```sh
composer config repositories.originchaindb path /absolute/path/to/originchaindb-php
composer require "originchain/originchaindb:@dev"
```

Composer supplies the PSR-4 autoloader. Commit the application's `composer.lock`
and keep its referenced checkout at the revision you have reviewed.

Pin a reviewed source commit in your application or build process. See the
[changelog](CHANGELOG.md) before updating.

## Connect and run your first query

Copy the complete database address from **Connect** into `OC_URL`, and the
database access token into `OC_TOKEN`. The SDK sends the token as a bearer
credential. It uses the address as supplied; do not add or derive a tenant path.

macOS/Linux shell:

```sh
export OC_URL="<database HTTPS address from Connect>"
export OC_TOKEN="<database access token>"
```

PowerShell:

```powershell
$env:OC_URL = "<database HTTPS address from Connect>"
$env:OC_TOKEN = "<database access token>"
```

Replace the placeholders, keep credentials out of source control, and use a
secret manager for deployed applications. HTTPS is required except for local
loopback development. SQL tools use a separate database login from Connect.

Save this as `app.php` beside your application's `vendor/` directory and run `php app.php`.

```php
<?php

require 'vendor/autoload.php';

use OriginChainDB\Client;

$db = Client::fromEnv();
try {
    $result = $db->sql->run('SELECT $1 AS value', [7]);
    print_r($result->rows);
} finally {
    $db->close();
}
```

The query needs no tables. Its returned row contains `value` equal to `7`.
`$1` binds the first parameter; `$2` binds the second. Bind values instead of
interpolating application input into SQL. Parameters do not substitute table or
column names; those identifiers must come from trusted application code.

## Product catalog example

The complete [catalog program](examples/catalog.php) creates a small product catalog and
demonstrates the same sequence shown below. It uses the included
[products.toml](examples/products.toml) schema and writes two rows plus search
data under `sdk_examples.products`. **Run it against a development database.**
Your token needs permission to register schemas and read/write the relevant data.

Run from the SDK checkout root after installation and setting the two variables:

```sh
php examples/catalog.php
```

The sections below are excerpts from that complete program. Reuse the `db` client from the quickstart.
Required imports are `OriginChainDB\Client` and Composer's autoloader; the complete file includes them.

### Register the schema

The schema declares string columns `id`, `name`, `description`, and `related_to`.
`id` is the primary key. The `related` relation connects a product to the product
named by `related_to`. Registration reads the TOML file; it does not require
you to construct a schema request manually.

```php
$schema = 'sdk_examples.products';
$toml = file_get_contents(__DIR__ . '/products.toml');
if ($toml === false) {
    throw new RuntimeException('Cannot read products.toml');
}
$db->schemas->register($toml);
```

### Write and read rows

Create the referenced product first, then the product pointing to it. `put`
writes the supplied row; `get` retrieves it by primary key. The example prints
the row for `p1`, named `Widget`.

```php
$db->rows->put($schema, ['id' => 'p2', 'name' => 'Gadget', 'description' => 'A red gadget']);
$db->rows->put($schema, [
    'id' => 'p1', 'name' => 'Widget', 'description' => 'A small blue widget', 'related_to' => 'p2',
]);
$product = $db->rows->get($schema, 'p1');
print_r($product);
```

For multiple rows, the rows namespace also provides a batch-put operation.
Treat its response as a batch report, not as a promise of transaction atomicity.

### Query with SQL

Use `sql.run` (with the language's casing) for a complete statement result.
`execute` is an alias; `query` is intended for SELECT results.

```php
$result = $db->sql->run('SELECT id, name FROM sdk_examples.products WHERE id = $1', ['p1']);
foreach ($result->rows as $row) {
    echo $row['id'] . ' ' . $row['name'] . PHP_EOL;
}
```

Use the same parameter binding for INSERT, UPDATE, and DELETE. Read the
server-reported write count rather than assuming a successful request changed a row.

### Search by vector similarity

Store an embedding with its product ID, then request up to five nearest hits.
The default metric is cosine. These four numbers are a small illustrative vector;
generate real embeddings with your chosen model, and use that same model and
dimension for stored vectors and queries. The SDK does not generate embeddings.

```php
$embedding = [0.1, 0.2, 0.3, 0.4];
$db->vector->put($schema, 'p1', $embedding, ['category' => 'tools']);
$hits = $db->vector->search($schema, $embedding, k: 5);
foreach ($hits as $hit) {
    echo $hit->id . ' ' . $hit->score . PHP_EOL;
}
```

Each hit exposes an ID, score, optional metadata, and the original hit. Do not
assume the score is a probability; its interpretation depends on the metric.

### Search text

Index the description explicitly, then use ranked BM25 search. Search defaults
to ten hits; the example asks for five. Boolean and phrase modes, highlighting,
fuzzy matching, and facets are available through the language's search options.

```php
$db->fts->index($schema, 'description', 'p1', 'A small blue widget');
$matches = $db->fts->search($schema, 'description', 'blue widget', k: 5);
foreach ($matches->hits as $hit) {
    echo $hit->doc_id . ' ' . $hit->score . PHP_EOL;
}
```

The result contains `hits`, optional `facets`, and the original response.
Hits have a document ID and score; unranked modes use zero for the score.
Writing a row does not imply this example's separate full-text or vector index
entry has been updated: update the relevant index when your application changes data.

### Follow graph relationships

The schema's `related` relation and the `p1.related_to = p2` row value create the
connection used here. The result includes the neighbor `p2`.

```php
$neighbors = $db->graph->neighbors($schema, 'p1', 'related');
print_r($neighbors);
```

The graph namespace also supports breadth-first traversal and Cypher queries.
Cypher parameters bind **by name**; SQL parameters bind **by position**.
See the [API guide](docs/api.md) and source links below for exact signatures.

## Understanding SQL results

Rows are Associative arrays; integers outside PHP's native range stay strings. SQL returns a result object rather than only an array:

| Member | Meaning |
| --- | --- |
| `kind` | Statement kind, such as select, insert, update or delete |
| `rows` | Returned rows, including RETURNING rows when the server supplies them |
| `columns` | Column names, when supplied |
| `columnTypes` | Column type metadata, when supplied |
| `rowsAffected` | Server-reported write count; missing means unknown |
| `rowsBuffered` | Buffered count, when supplied; it is not proof of a committed write |
| `results` | Individual results for a multi-statement response |
| `raw` | Original response, including additional server fields |

Write counts use `?int`; `null` means the server did not report
a count. Empty rows are not evidence of a failed statement: a write may return
only counts. Conversely, a malformed response is an error, not an empty success.

Numeric and temporal **strings** remain strings. Convert them deliberately in
your application when you know the column type. Unsupported write-count ranges
raise a protocol error instead of silently rounding.

Multi-statement batches can partially succeed. A failed batch exposes the
available statement results through its error. Do not assume batches are atomic
or replay an entire batch without checking which statements completed.

## Timeouts and client lifetime

The default timeout is 60 seconds. Set it in seconds:

```php
$db = Client::fromEnv(timeout: 10.0);
try {
    $result = $db->sql->run('SELECT $1 AS value', [7]);
} finally {
    $db->close();
}
```

The client reuses its cURL handle for sequential requests. Use one client per
worker rather than sharing a handle for overlapping operations. On Windows,
the native certificate store is used when no custom cURL CA file is configured.

Requests are **not retried automatically by the SDK by default**, and redirects
are not followed. A timeout or disconnect can happen after a write committed.
Canceling the local wait does not prove rollback. Only retry an operation when
your application can safely reconcile or repeat it.

## Handling errors

```php
use OriginChainDB\Exception as OriginChainException;

try {
    $result = $db->sql->run('SELECT $1 AS value', [7]);
} catch (OriginChainException $error) {
    echo "HTTP {$error->status}, request ID {$error->requestId}" . PHP_EOL;
    if ($error->partial !== null) {
        echo 'Partial batch: ' . count($error->partial->results) . PHP_EOL;
    }
}
```

Inspect `kind` to distinguish HTTP, transport, timeout, protocol, and batch
failures. Invalid arguments raise `InvalidArgumentException`.

| Symptom | What to check |
| --- | --- |
| 401 or 403 | Token validity, expiry, database access and operation grants |
| 404 | Complete database address, schema name and resource ID |
| 400 or 422 | SQL syntax, parameter order, schema fields and vector dimensions |
| 429 | Capacity/rate limits and any server-provided retry timing |
| Timeout or 5xx after a write | Check the outcome before retrying |
| Empty search results | Indexed data, query terms, dimensions, filters and feature availability |

Include the SDK version and request ID in a support report. Redact credentials
and application data. Available database features and token permissions still
determine which operations can run.

## Source layout

| Path | Purpose |
| --- | --- |
| [src/Client.php](src/Client.php) | Client, authentication and cURL transport |
| [src/Sql.php](src/Sql.php), [src/Rows.php](src/Rows.php) | SQL and row operations |
| [src/Vector.php](src/Vector.php), [src/Fts.php](src/Fts.php), [src/Graph.php](src/Graph.php) | Search and graph services |
| [src/Exception.php](src/Exception.php), [src/SqlResult.php](src/SqlResult.php) | Errors and SQL results |
| [examples/](examples/) | Quickstart, product catalog and schema |
| [tests/contract/run.py](tests/contract/run.py) | Shared offline HTTP conformance fixture |
| [docs/api.md](docs/api.md), [docs/contract.md](docs/contract.md) | Native API notes and shared behavior |

All eight SDKs share the same core concepts and HTTP contract. Method casing,
JSON types and concurrency follow each language. Historical Python/TypeScript
helpers are broader than this common core; do not assume every advanced helper
exists in every SDK.

## Development and support

From the checkout root:

```sh
composer validate --strict
composer install
python tests/contract/run.py -- php tests/consumer.php
```

The contract runner requires Python 3.10+ for testing only and uses a local
fixture; it does not require database credentials. Application runtime
requirements are listed at the top. Live examples use your selected database.

Read [Contributing](CONTRIBUTING.md) before sending a change. Report bugs through
[GitHub issues](https://github.com/originchaindb/originchaindb-php/issues); report security concerns
privately as described in [Security](SECURITY.md). See [LICENSE](LICENSE) for MIT terms.
