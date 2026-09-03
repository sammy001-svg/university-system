<?php
/**
 * Command-line installer.
 *
 *   php database/install.php            create schema + core seed + demo data
 *   php database/install.php --fresh    drop and recreate the database first
 *   php database/install.php --no-demo  schema + core seed only
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("This installer must be run from the command line.\n");
}

$base = dirname(__DIR__);
require_once $base . '/app/Core/Config.php';
require_once $base . '/app/Core/Database.php';
require_once $base . '/app/Core/Hash.php';
require_once $base . '/app/Core/Logger.php';

use App\Core\Config;
use App\Core\Database;

Config::load(require $base . '/config/config.php');

$options = getopt('', ['fresh', 'no-demo', 'help']);
if (isset($options['help'])) {
    echo "Usage: php database/install.php [--fresh] [--no-demo]\n";
    exit(0);
}

$cfg    = Config::get('database');
$dbName = $cfg['name'];

echo "University Management System - installer\n";
echo str_repeat('=', 46) . "\n";
echo "Server : {$cfg['host']}:{$cfg['port']}\n";
echo "Database: {$dbName}\n\n";

/* 1. Connect to the server without selecting a database. */
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=%s', $cfg['host'], $cfg['port'], $cfg['charset']),
        $cfg['user'],
        $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    exit("Could not connect to MySQL: " . $e->getMessage() . "\nCheck the DB_* values in your .env file.\n");
}

if (isset($options['fresh'])) {
    echo "Dropping existing database...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$dbName}`");

/* 2. Apply the schema. */
echo "Applying schema...\n";
$schema = file_get_contents($base . '/database/schema.sql');
if ($schema === false) {
    exit("Could not read database/schema.sql\n");
}
// The schema file creates and selects its own database; strip those lines so the
// configured database name from .env wins.
$schema = preg_replace('/CREATE DATABASE[^;]+;/i', '', $schema);
$schema = preg_replace('/USE\s+`?[a-zA-Z0-9_]+`?\s*;/i', '', (string) $schema);

$statements = array_filter(
    array_map('trim', explode(";\n", (string) $schema)),
    static fn (string $s): bool => $s !== '' && !str_starts_with($s, '--')
);

$applied = 0;
foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }
    try {
        $pdo->exec($statement);
        $applied++;
    } catch (PDOException $e) {
        echo "  ! statement failed: " . substr(preg_replace('/\s+/', ' ', $statement), 0, 90) . "...\n";
        echo "    " . $e->getMessage() . "\n";
    }
}
echo "  {$applied} statements applied.\n";

$tables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$dbName}'")->fetchColumn();
echo "  {$tables} tables present.\n\n";

/* 3. Seed. */
echo "Seeding core data (roles, permissions, settings, grading scale)...\n";
$core = require $base . '/database/seeds/core.php';
$core($pdo);

if (!isset($options['no-demo'])) {
    echo "Seeding demonstration data...\n";
    $demo = require $base . '/database/seeds/demo.php';
    $demo($pdo);
}

echo "\nInstallation complete.\n";
echo str_repeat('-', 46) . "\n";
echo "Sign in at: " . Config::get('app.url') . "/login\n";
echo "  Administrator : admin      / Admin@2026\n";
if (!isset($options['no-demo'])) {
    echo "  Registrar     : registrar  / Staff@2026\n";
    echo "  Finance       : bursar     / Staff@2026\n";
    echo "  Lecturer      : vtubman    / Staff@2026\n";
    echo "  Student       : mjohnson   / Student@2026\n";
}
echo "\nChange these passwords immediately after the first sign-in.\n";
