<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
use OriginChainDB\Client;
use OriginChainDB\Exception as OriginChainException;

$db = Client::fromEnv();
try {
    // section:setup
    $schema = 'sdk_examples.products';
    $toml = file_get_contents(__DIR__ . '/products.toml');
    if ($toml === false) {
        throw new RuntimeException('Cannot read products.toml');
    }
    $db->schemas->register($toml);
    // endsection

    // section:rows
    $db->rows->put($schema, ['id' => 'p2', 'name' => 'Gadget', 'description' => 'A red gadget']);
    $db->rows->put($schema, [
        'id' => 'p1', 'name' => 'Widget', 'description' => 'A small blue widget', 'related_to' => 'p2',
    ]);
    $product = $db->rows->get($schema, 'p1');
    print_r($product);
    // endsection

    // section:sql
    $result = $db->sql->run('SELECT id, name FROM sdk_examples.products WHERE id = $1', ['p1']);
    foreach ($result->rows as $row) {
        echo $row['id'] . ' ' . $row['name'] . PHP_EOL;
    }
    // endsection

    // section:vector
    $embedding = [0.1, 0.2, 0.3, 0.4];
    $db->vector->put($schema, 'p1', $embedding, ['category' => 'tools']);
    $hits = $db->vector->search($schema, $embedding, k: 5);
    foreach ($hits as $hit) {
        echo $hit->id . ' ' . $hit->score . PHP_EOL;
    }
    // endsection

    // section:fts
    $db->fts->index($schema, 'description', 'p1', 'A small blue widget');
    $matches = $db->fts->search($schema, 'description', 'blue widget', k: 5);
    foreach ($matches->hits as $hit) {
        echo $hit->doc_id . ' ' . $hit->score . PHP_EOL;
    }
    // endsection

    // section:graph
    $neighbors = $db->graph->neighbors($schema, 'p1', 'related');
    print_r($neighbors);
    // endsection
} catch (OriginChainException $error) {
    fwrite(STDERR, "Request failed: {$error->kind}, HTTP {$error->status}, request ID {$error->requestId}
");
    throw $error;
} finally {
    $db->close();
}
