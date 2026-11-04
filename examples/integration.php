<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use OriginChainDB\Client;
use OriginChainDB\RequestEvent;

final class Product
{
    public function __construct(public readonly string $id, public readonly string $name) {}
}

$client = new Client(getenv('OC_URL') ?: '',
    token: fn(): string => getenv('OC_TOKEN') ?: '',
    onRequest: function (RequestEvent $event): void {
        printf("%s %s %.3fs\n", $event->operation, $event->status ?? '-', $event->duration);
    },
    // For a private CA: httpOptions: [CURLOPT_CAINFO => '/path/to/ca.pem']
);
try {
    $result = $client->sql->run('SELECT $1 AS id, $2 AS name', ['p1', 'Widget']);
    $products = $result->mapRows(fn(array $row) => new Product($row['id'], $row['name']));
    foreach ($products as $product) { echo $product->id . ': ' . $product->name . PHP_EOL; }
} finally { $client->close(); }
