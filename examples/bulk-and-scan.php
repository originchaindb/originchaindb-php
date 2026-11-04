<?php

declare(strict_types=1);
// Run against a development database; writes sdk_iteration.items.
require __DIR__ . '/../vendor/autoload.php';
use OriginChainDB\Client;

function input(): Generator
{
    foreach (str_split('abcde') as $id) {
        yield ['id' => $id, 'name' => 'Item ' . $id];
    }
}
$db = Client::fromEnv();
try {
    $schema = 'sdk_iteration.items';
    $toml = file_get_contents(__DIR__ . '/items.toml');
    if ($toml === false) {
        throw new RuntimeException('Cannot read items.toml');
    }
    $db->schemas->register($toml);
    $rows = input();
    // section:bulk
    foreach ($db->rows->writeBatches($schema, $rows, batchSize: 2) as $batch) {
        printf("%d %d %d\n", $batch->offset, $batch->count, $batch->inserted);
    }
    // endsection
    // section:scan
    foreach ($db->rows->scan($schema, 'id', pageSize: 2) as $row) {
        echo $row['id'] . ' ' . $row['name'] . "\n";
    }
    // endsection
    // Save a checkpoint only after successfully processing the complete page.
    foreach ($db->rows->pages($schema, 'id', pageSize: 2, after: 'b') as $page) {
        foreach ($page->rows as $row) {
            echo 'resumed ' . $row['id'] . "\n";
        }
        echo 'next cursor ' . ($page->nextCursor ?? 'complete') . "\n";
    }
} finally {
    $db->close();
}
