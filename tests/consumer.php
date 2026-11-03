<?php

declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    $prefix = 'OriginChainDB\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__ . '/../src/' . substr($class, strlen($prefix)) . '.php';
    }
});
use OriginChainDB\Client;
use OriginChainDB\Exception;

function check(bool $value): void
{
    if (!$value) {
        throw new \RuntimeException('Contract assertion failed');
    }
}
$c = Client::fromEnv();
$r = $c->sql->run('contract:select', [7, "quote' / 雪", null]);
check((string)$r->rows[0]['id'] === '9007199254740993');
check($r->rows[0]['amount'] === '1.00000000000000001' && $r->raw->future_field->kept);
$r = $c->sql->run('contract:update');
check($r->rowsAffected === 2 && $r->rowsBuffered === 3 && count($r->rows) === 1 && $r->columns === ['id']);
$r = $c->sql->run('contract:unknown');
check($r->kind === 'future_statement' && $r->rowsAffected === null);
foreach (['batch', 'unavailable', 'unauthorized', 'redirect', 'malformed', 'invalid-rows'] as $name) {
    $caught = false;
    try {
        $c->sql->run('contract:' . $name);
    } catch (Exception $e) {
        $caught = true;
        if ($name === 'batch') {
            check($e->partial->results[0]->rowsAffected === 1);
        }
        if ($name === 'unauthorized') {
            check($e->status === 401 && $e->requestId === '84f92822-5837-432e-981a-7b24ad65e16c');
        }
        if ($name === 'unavailable') {
            check($e->status === 503 && $e->retryAfter === '0');
        }
    }
    check($caught);
}
$c->rows->get('demo/items', 'a b?/%');
$c->rows->put('demo.items', ['id' => 'one', 'nullable' => null]);
$c->rows->putBatch('demo.items', [['id' => 'one'], ['id' => 'two']]);
$c->schemas->list();
$c->schemas->register("[schema]\nname=\"demo.items\"\n");
$c->vector->put('demo.items', 'one', [1.0, 0.0], ['tag' => 'test']);
$vectors = $c->vector->search('demo.items', [1.0, 0.0]);
check($vectors[0]->id === 'one' && $vectors[1]->id === 'two');
$c->vector->delete('demo.items', 'one');
$c->fts->index('demo.items', 'body', 'one', 'hello world');
check($c->fts->search('demo.items', 'body', 'hello')->hits[0]->doc_id === 'one');
$phrase = $c->fts->search('demo.items', 'body', 'phrase', mode: 'phrase');
check($phrase->hits[0]->doc_id === 'two' && $phrase->hits[0]->score === 0.0);
$enriched = $c->fts->search('demo.items', 'body', 'highlight', highlight: true, facets: ['tag']);
check($enriched->facets !== null && $enriched->hits[0]->highlights !== null);
$c->graph->query('MATCH (n) RETURN $name', ['name' => 'one']);
$c->graph->neighbors('demo.items', 'one', 'related');
$c->graph->bfs('demo.items', 'one', 'related', 5);
$c->close();
echo "PHP consumer assertions passed\n";
