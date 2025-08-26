<?php

require_once __DIR__ . '/../api/lib/Database.php';

$email = $argv[1] ?? 'admin@example.com';
$name = $argv[2] ?? 'Admin User';
$password = $argv[3] ?? 'AdminPass123!';

try {
    $pdo = Database::getConnection();
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->beginTransaction();
    $pdo->exec("INSERT INTO roles (role_name) VALUES ('ICT Admin') ON CONFLICT (role_name) DO NOTHING");
    $roleId = $pdo->query("SELECT role_id FROM roles WHERE role_name='ICT Admin'")->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role_id) VALUES (:n, :e, :p, :r)');
    $stmt->execute([':n' => $name, ':e' => $email, ':p' => $hash, ':r' => $roleId]);
    $pdo->commit();
    echo "Bootstrap user created: $email\n";
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}


