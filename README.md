# OriginChainDB for PHP

The official PHP client for SQL, vector search, full-text search, and graph queries.

[![CI](https://github.com/originchaindb/originchaindb-php/actions/workflows/ci.yml/badge.svg)](https://github.com/originchaindb/originchaindb-php/actions/workflows/ci.yml)

## Get started

Requires **PHP 8.2+ with cURL and JSON**. This is the unpublished 1.0 release candidate. Build from
source and pin a reviewed commit; registry publication is currently on hold.

```sh
git clone https://github.com/originchaindb/originchaindb-php.git
cd originchaindb-php
composer install
```

To use this checkout from another application, run these commands in that
application, replacing the path with your SDK checkout:

```sh
composer config repositories.originchaindb path /absolute/path/to/originchaindb-php
composer require "originchain/originchaindb:@dev"
```

Copy your database address and access token from Connect into `OC_URL` and
`OC_TOKEN`. Keep tokens in your server's environment or secret manager.

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

Reuse the client for your application's lifetime. Pass values as parameters;
do not interpolate application data into query text.

## One API family

Use `sql`, `schemas`, `rows`, `vector`, `fts`, and `graph` for the same database
concepts across every OriginChainDB SDK. Names use this language's normal casing.

- SQL results include returned rows, confirmed counts, metadata and batch results.
- Full-text search provides the same hit structure for ranked and unranked queries.
- Access tokens are sent as bearer credentials to the exact database address.
- Failed writes are not retried automatically.

[Using the client](docs/api.md) Â· [Behavior](docs/contract.md) Ã‚Â· [Contributing](CONTRIBUTING.md) Ã‚Â·
[Changes](CHANGELOG.md) Ã‚Â· [Security](SECURITY.md)

## Development

```sh
composer validate --strict
composer install
python tests/contract/run.py -- php tests/consumer.php
```

The HTTP contract runner requires Python 3.10+ for testing only. No database
credentials are needed for the offline suite. Native runtime and dependency
requirements above apply to applications.
