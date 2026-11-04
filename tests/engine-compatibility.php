<?php

declare(strict_types=1);
// Private integration worker. Credentials arrive over stdin and are never logged.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'OriginChainDB\\')) {
        require __DIR__ . '/../src/' . substr($class, 14) . '.php';
    }
});
function check(bool $value): void
{
    if (!$value) {
        throw new RuntimeException('Engine compatibility assertion failed');
    }
}
$current = 'unset';
$calls = 0;
$db = new OriginChainDB\Client(getenv('OC_URL'), function () use (&$current, &$calls): string {
    $calls++;
    return $current;
});
try {
    while (($line = fgets(STDIN)) !== false) {
        $cmd = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        $current = $cmd['token'];
        $before = $calls;
        $status = 200;
        try {
            if ($cmd['action'] === 'query') {
                $result = $db->sql->run($cmd['sql'], $cmd['params'] ?? []);
                check($cmd['status'] === 200 && count($result->rows) === ($cmd['rows'] ?? 0));
            } elseif ($cmd['action'] === 'bulk') {
                $input = [];
                foreach (str_split('abcde') as $value => $id) {
                    $input[] = ['id' => $id, 'value' => $value];
                }
                $reports = iterator_to_array($db->rows->writeBatches($cmd['schema'], $input, batchSize:2));
                check(array_map(fn ($b) => [$b->offset,$b->count,$b->inserted], $reports) === [[0,2,2],[2,2,2],[4,1,1]]);
            } elseif ($cmd['action'] === 'scan') {
                check(array_column(iterator_to_array($db->rows->scan($cmd['schema'], 'id', pageSize:2)), 'id') === str_split('abcde'));
            } else {
                throw new RuntimeException('Unknown compatibility action');
            }
        } catch (OriginChainDB\Exception $error) {
            $status = $error->status;
            check($cmd['status'] !== 200 && $status === $cmd['status']);
        }
        check($calls - $before === $cmd['requests']);
        echo json_encode(['ok' => true,'status' => $status,'requests' => $calls - $before], JSON_THROW_ON_ERROR) . "\n";
        fflush(STDOUT);
    }
} finally {
    $db->close();
}
