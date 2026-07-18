<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Cloudexus\Core\Config;
use Cloudexus\Core\DatabaseConnection;

Config::load(dirname(__DIR__) . '/config/config.ini');

$pdo = DatabaseConnection::get();
$files = glob(__DIR__ . '/*/*.sql');

sort($files);

foreach ($files as $file) {
    echo "Running $file ...\n";
    $pdo->exec(file_get_contents($file));
}

echo "Done.\n";
