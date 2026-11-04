<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    $prefix = 'OriginChainDB\\';
    if (str_starts_with($class, $prefix)) { require __DIR__ . '/../src/' . substr($class, strlen($prefix)) . '.php'; }
});
use OriginChainDB\Client;
use OriginChainDB\Exception;
use OriginChainDB\RowDecodeException;
function check(bool $value): void { if (!$value) { throw new \RuntimeException('Resilience assertion failed'); } }
function fails(callable $run): Exception {
    try { $run(); } catch (Exception $error) { return $error; }
    throw new \RuntimeException('Expected SDK failure');
}
$url = getenv('OC_URL'); $token = getenv('OC_TOKEN');
$client = new Client($url, $token);
foreach (['expired'=>401, 'revoked'=>401, 'forbidden'=>403, 'limited'=>429] as $name=>$status) {
    $error = fails(fn() => $client->sql->run('fault:' . $name));
    check($error->status === $status && $error->requestId === '84f92822-5837-432e-981a-7b24ad65e16c');
    if ($name === 'limited') { check($error->retryAfter === '2'); }
}
fails(fn() => $client->sql->run('fault:interrupted'));
$client->close();
$slow = new Client($url, $token, 0.15);
check(fails(fn() => $slow->sql->run('fault:timeout'))->kind === 'timeout');
$slow->close();
$calls = 0; $events = [];
$rotating = new Client($url, function () use (&$calls): string { return 'rotate-' . ++$calls; },
    onRequest: function ($event) use (&$events): void { $events[] = $event; });
check(fails(fn() => $rotating->sql->run('fault:rotate'))->status === 401 && $calls === 1);
$result = $rotating->sql->run('fault:rotate');
$rotating->close();
check($calls === 2 && count($events) === 2 && $events[0]->errorKind === 'authentication' && $events[1]->errorKind === null);
check($events[1]->operation === 'sql' && $events[1]->duration > 0);
check(!str_contains(json_encode($events, JSON_THROW_ON_ERROR), 'rotate-') && !str_contains(json_encode($events, JSON_THROW_ON_ERROR), $url));
final class Product { public function __construct(public int $id, public string $amount) {} }
$products = $result->mapRows(fn(array $row) => new Product($row['id'], $row['amount']));
check($products[0]->id === 9007199254740993 && $products[0]->amount === '1.00000000000000001');
$result->mapRows(function (array $row) { $row['id'] = 'changed'; return $row; });
check($result->rows[0]['id'] === 9007199254740993);
try { $result->mapRows(fn(array $row) => new Product($row['amount'], 'bad')); throw new \RuntimeException('Invalid mapping accepted'); }
catch (RowDecodeException $error) { check($error->row === 0); }
$invalid = new Client($url, fn() => "bad\nheader");
check(fails(fn() => $invalid->sql->run('fault:invalid-provider'))->kind === 'credential');
$invalid->close();
$observed = new Client($url, $token, onRequest: function ($event): void { throw new \RuntimeException('observer'); });
check(count($observed->sql->run('fault:observer')->rows) === 1);
$observed->close();
echo "PHP resilience assertions passed\n";
