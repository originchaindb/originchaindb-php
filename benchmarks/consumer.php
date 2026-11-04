<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'OriginChainDB\\')) { require __DIR__ . '/../src/' . substr($class, 14) . '.php'; }
});
$db = OriginChainDB\Client::fromEnv();
$query = static function (string $mode) use ($db): float {
    $start = hrtime(true);
    $result = $db->sql->query('bench:' . $mode);
    if (count($result->rows) !== ($mode === 'large' ? 2048 : 1)) { throw new RuntimeException('Unexpected result size'); }
    return (hrtime(true) - $start) / 1e6;
};
$report = [];
try {
    if (getenv('OC_BENCH_WORKER') === '1') {
        $report['concurrent_ms'] = [];
        for ($i = 0; $i < 8; $i++) { $report['concurrent_ms'][] = $query('concurrent'); }
    } else {
        for ($i = 0; $i < 10; $i++) { $query('warm'); }
        foreach (['sequential' => 100, 'large' => 10] as $mode => $count) {
            $start = hrtime(true); $report[$mode . '_ms'] = [];
            for ($i = 0; $i < $count; $i++) { $report[$mode . '_ms'][] = $query($mode); }
            $report[$mode . '_rps'] = $count * 1e9 / (hrtime(true) - $start);
        }
    }
} finally { $db->close(); }
echo 'OC_BENCHMARK ' . json_encode($report, JSON_THROW_ON_ERROR) . "\n";
