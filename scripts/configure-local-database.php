<?php

// Only used by the isolated Windows development database helper.
$root = dirname(__DIR__);
$local = $root.'/.local/mysql';
$credentialsFile = $local.'/credentials.json';
$credentials = file_exists($credentialsFile)
    ? json_decode(file_get_contents($credentialsFile), true, flags: JSON_THROW_ON_ERROR)
    : ['root' => bin2hex(random_bytes(24)), 'application' => bin2hex(random_bytes(24))];

file_put_contents($credentialsFile, json_encode($credentials, JSON_THROW_ON_ERROR));

$dsn = 'mysql:host=127.0.0.1;port=3307;charset=utf8mb4';
try {
    $pdo = new PDO($dsn, 'root', $credentials['root'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException) {
    // A newly initialized, loopback-only instance initially has an empty root password.
    $pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

$appPassword = $pdo->quote($credentials['application']);
$pdo->exec('CREATE DATABASE IF NOT EXISTS lgu_payroll CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec("CREATE USER IF NOT EXISTS 'lgu_payroll'@'127.0.0.1' IDENTIFIED BY $appPassword");
$pdo->exec("ALTER USER 'lgu_payroll'@'127.0.0.1' IDENTIFIED BY $appPassword");
$pdo->exec("GRANT ALL PRIVILEGES ON lgu_payroll.* TO 'lgu_payroll'@'127.0.0.1'");
$pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$pdo->quote($credentials['root']));

file_put_contents($local.'/admin.cnf', "[client]\nhost=127.0.0.1\nport=3307\nuser=root\npassword=".$credentials['root']."\n");

$envPath = $root.'/application/.env';
$env = file_get_contents($envPath);
$values = [
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3307',
    'DB_DATABASE' => 'lgu_payroll',
    'DB_USERNAME' => 'lgu_payroll',
    'DB_PASSWORD' => $credentials['application'],
];
foreach ($values as $key => $value) {
    $pattern = '/^(?:#\s*)?'.preg_quote($key, '/').'=.*$/m';
    $env = preg_match($pattern, $env)
        ? preg_replace($pattern, $key.'='.$value, $env)
        : $env."\n".$key.'='.$value;
}
file_put_contents($envPath, $env);
echo "Development database and restricted application account configured.\n";
