<?php
/**
 * Aadi Marketing OS read-only Hostinger CLI preflight.
 * DDL probe is explicitly opt-in. This file must NEVER be served via HTTP.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$requiredExtensions = [
    'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash',
    'mbstring', 'openssl', 'pcre', 'pdo', 'session', 'tokenizer', 'xml',
];

$available = array_map('strtolower', get_loaded_extensions());
$extensionStatus = [];
foreach ($requiredExtensions as $extension) {
    $extensionStatus[$extension] = in_array($extension, $available, true);
}

$report = [
    'tool' => 'aadi_hostinger_preflight_v1',
    'time_utc' => gmdate('c'),
    'execution_mode' => 'cli',
    'php_version' => PHP_VERSION,
    'php_binary' => PHP_BINARY,
    'php_sapi' => PHP_SAPI,
    'php_ini_loaded' => php_ini_loaded_file() !== false,
    'required_extensions' => $extensionStatus,
    'missing_required_extensions' => array_keys(array_filter($extensionStatus, static fn(bool $ok): bool => !$ok)),
    'pdo_drivers' => class_exists('PDO') ? PDO::getAvailableDrivers() : [],
    'settings' => [
        'memory_limit' => (string) ini_get('memory_limit'),
        'max_execution_time' => (string) ini_get('max_execution_time'),
        'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
        'post_max_size' => (string) ini_get('post_max_size'),
        'date_timezone' => (string) ini_get('date.timezone'),
    ],
    'laravel13_php_minimum_satisfied' => version_compare(PHP_VERSION, '8.3.0', '>='),
    'db' => ['status' => 'not_requested'],
    'cron' => ['status' => 'manual_hpanel_check_required'],
    'document_root' => ['status' => 'manual_check_required'],
];

$dbHost = getenv('PREFLIGHT_DB_HOST');
$dbName = getenv('PREFLIGHT_DB_NAME');
$dbUser = getenv('PREFLIGHT_DB_USER');
$dbPassword = getenv('PREFLIGHT_DB_PASSWORD');
$probeDdl = in_array('--probe-db-ddl', $argv, true);

if ($dbHost !== false && $dbName !== false && $dbUser !== false) {
    if (!in_array('mysql', $report['pdo_drivers'], true)) {
        $report['db'] = ['status' => 'pdo_mysql_missing'];
    } else {
        try {
            // Database name is interpolated only after a conservative allowlist check.
            if (!preg_match('/^[A-Za-z0-9_]+$/D', $dbName)) {
                throw new InvalidArgumentException('Invalid DB name');
            }
            if (!preg_match('/^[A-Za-z0-9.:-]+$/D', $dbHost)) {
                throw new InvalidArgumentException('Invalid DB host');
            }
            $port = getenv('PREFLIGHT_DB_PORT');
            $port = $port !== false ? (int) $port : 3306;
            if ($port < 1 || $port > 65535) {
                throw new InvalidArgumentException('Invalid DB port');
            }
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $port, $dbName),
                $dbUser,
                $dbPassword !== false ? $dbPassword : '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            $serverVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $report['db'] = ['status' => 'connected', 'server_version' => $serverVersion, 'ddl_probe' => 'not_requested'];
            if ($probeDdl) {
                $table = 'aadi_preflight_' . bin2hex(random_bytes(6));
                $created = false;
                try {
                    $pdo->exec(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY) ENGINE=InnoDB', $table));
                    $created = true;
                    $pdo->exec(sprintf('DROP TABLE `%s`', $table));
                    $report['db']['ddl_probe'] = 'create_and_drop_succeeded';
                } catch (PDOException $e) {
                    $report['db']['ddl_probe'] = 'failed';
                    $report['db']['ddl_error_sqlstate'] = (string) $e->getCode();
                    // Attempt cleanup if table creation succeeded.
                    if ($created) {
                        try { $pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table)); }
                        catch (PDOException $ignored) { $report['db']['ddl_cleanup'] = 'failed_manual_check'; }
                    }
                }
            }
        } catch (Throwable $e) {
            // Avoid leaking host, username, connection details or database errors.
            $report['db'] = ['status' => 'connection_or_configuration_failed', 'error_class' => get_class($e)];
        }
    }
} elseif ($probeDdl) {
    $report['db'] = ['status' => 'missing_db_environment_for_ddl_probe'];
}

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
echo $json . PHP_EOL;
