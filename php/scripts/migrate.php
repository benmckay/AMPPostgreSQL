<?php

// Simple migration runner using PDO (no psql required)

require_once __DIR__ . '/../api/lib/Database.php';

try {
    $pdo = Database::getConnection();
    $dir = realpath(__DIR__ . '/../../db/migrations');
    if (!$dir || !is_dir($dir)) { fwrite(STDERR, "Migrations dir not found\n"); exit(1); }
    $files = glob($dir . '/*.sql');
    natsort($files);
    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if (!$sql) continue;
        $pdo->exec($sql);
        echo "Applied: " . basename($file) . "\n";
    }
    echo "Migrations applied successfully\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}


