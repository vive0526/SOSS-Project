<?php

declare(strict_types=1);

function envValue(string $key, ?string $default = null): ?string
{
    static $env = null;
    if ($env === null) {
        $env = [];
        $path = __DIR__ . '/../.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                $v = trim($v, "\"'");
                $env[$k] = $v;
            }
        }
    }

    return $env[$key] ?? $default;
}

$host = envValue('DB_HOST', '127.0.0.1');
$port = envValue('DB_PORT', '3306');
$db = envValue('DB_DATABASE', 'soss');
$user = envValue('DB_USERNAME', 'root');
$pass = envValue('DB_PASSWORD', '');

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "DB connect failed: {$e->getMessage()}\n");
    exit(2);
}

$rows = $pdo->query('SELECT id, name FROM categories ORDER BY id')->fetchAll();
echo "categories_count=" . count($rows) . "\n";
foreach ($rows as $row) {
    echo $row['id'] . "\t" . $row['name'] . "\n";
}

