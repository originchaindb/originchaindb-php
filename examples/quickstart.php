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
