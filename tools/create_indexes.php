<?php
require __DIR__ . '/../vendor/autoload.php';

echo "SQLite schema and indexes are created automatically.\n";
echo "Database: " . dotship_config()['sqlite_path'] . PHP_EOL;
