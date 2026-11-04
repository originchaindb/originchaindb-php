<?php

declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'OriginChainDB\\')) {
        require __DIR__ . '/../src/' . substr($class, 14) . '.php';
    }
});
use OriginChainDB\{Client, BatchWriteException, Exception};

function check(bool $value): void
{
    if (!$value) {
        throw new \RuntimeException('Iteration assertion failed');
    }
}
function source(int &$consumed): Generator
{
    $row = [];
    foreach (str_split('abcde') as $id) {
        $consumed++;
        $row['id'] = $id;
        yield $row;
    }
}
$db = Client::fromEnv();
$n = 0;
$batches = $db->rows->writeBatches('bulk.ok', source($n), batchSize: 2);
check($n === 0);
$first = $batches->current();
check($n === 2 && $first->offset === 0 && $first->count === 2 && $first->inserted === 2);
$batches->next();
check($batches->current()->offset === 2);
$batches->next();
check($batches->current()->offset === 4 && $batches->current()->count === 1);
$batches->next();
check(!$batches->valid());
foreach (['fail' => [2, 4], 'lost' => [0, 2], 'unknown' => [0, 2]] as $name => [$offset, $consumed]) {
    $n = 0;
    try {
        iterator_to_array($db->rows->writeBatches('bulk.' . $name, source($n), batchSize: 2));
        throw new RuntimeException('Expected failure');
    } catch (BatchWriteException $error) {
        check($error->offset === $offset && $error->count === 2 && $error->outcome === 'unknown' && $error->getPrevious() !== null && $n === $consumed);
    }
}
$reports = iterator_to_array($db->rows->writeBatches('bulk.bytes', array_map(fn ($id) => ['id' => $id, 'value' => str_repeat('x', 20)], str_split('abc')), maxBytes: 64));
check(array_map(fn ($r) => $r->count, $reports) === [1,1,1]);
$n = 0;
foreach ($db->rows->writeBatches('bulk.stop', source($n), batchSize: 2) as $b) {
    break;
} check($n === 2);
check(iterator_to_array($db->rows->writeBatches('bulk.never', [])) === []);
try {
    $db->rows->writeBatches('bulk.never', [['id' => 'large']], maxBytes: 4)->current();
    throw new RuntimeException('Expected failure');
} catch (BatchWriteException $error) {
    check($error->outcome === 'not_sent' && $error->offset === 0);
}
try {
    $db->rows->writeBatches('bulk.never', [], batchSize: 0)->current();
    throw new RuntimeException('Expected failure');
} catch (InvalidArgumentException) {
}
try {
    $db->rows->writeBatches('bulk.never', source($n), cancelled: fn () => true)->current();
    throw new RuntimeException('Expected failure');
} catch (Exception $e) {
    check($e->kind === 'cancelled');
}
check(array_column(iterator_to_array($db->rows->scan('scan.items', 'id', pageSize: 2)), 'id') === str_split('abcde'));
foreach ($db->rows->scan('scan.stop', 'id', pageSize: 2) as $row) {
    check($row['id'] === 'a');
    break;
}
$pages = iterator_to_array($db->rows->pages('scan.resume', 'id', pageSize: 2, after: 'b'));
check(array_map(fn ($p) => $p->nextCursor, $pages) === ['d', null]);
foreach (['bad', 'numeric', 'oversize'] as $name) {
    try {
        $db->rows->pages('scan.' . $name, 'id', pageSize: 2)->current();
        throw new RuntimeException('Expected failure');
    } catch (Exception $e) {
        check($e->kind === 'protocol');
    }
}
check(iterator_to_array($db->rows->scan('scan.empty', 'id', pageSize: 2)) === []);
try {
    $db->rows->scan('scan.items;DELETE', 'id')->current();
    throw new RuntimeException('Expected failure');
} catch (InvalidArgumentException) {
}
$db->close();
echo "PHP iteration assertions passed\n";
