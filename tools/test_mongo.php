<?php
try {
    $manager = new MongoDB\Driver\Manager('mongodb://127.0.0.1:27017');
    $cmd = new MongoDB\Driver\Command(['ping' => 1]);
    $cursor = $manager->executeCommand('admin', $cmd);
    $result = current($cursor->toArray());
    echo "OK: ";
    var_export($result);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
    exit(1);
}
