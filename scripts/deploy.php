<?php
/**
 * Idempotent first-deploy / upgrade entry point.
 * Usage: php scripts/deploy.php --check | --apply [--create-database]
 * No network access or destructive SQL runs without explicit --apply.
 * Requires existing MySQL server credentials; CREATE DATABASE is attempted only
 * when DB_AUTO_CREATE=true and the MySQL account has CREATE privilege.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
$check = in_array('--check', $argv, true) || !$apply;
$create = in_array('--create-database', $argv, true);
if (in_array('--help',$argv,true)) {
    fwrite(STDOUT,"Usage: php scripts/deploy.php --check|--apply [--create-database]\n"); exit(0);
}
$required = ['pdo_mysql','mbstring','openssl','fileinfo','curl','dom','xml'];
$missing = array_values(array_filter($required, static fn(string $e):bool => !extension_loaded($e)));
if (PHP_VERSION_ID < 80300 || $missing) {
    fwrite(STDERR, 'BLOCKED: PHP >=8.3 and extensions required: '.implode(', ', $missing)."\n"); exit(2);
}
$vendor = is_file($root.'/vendor/autoload.php');
if (!$vendor) {
    fwrite(STDERR,"BLOCKED: run composer install --no-dev --optimize-autoloader first.\n"); exit(2);
}
$envPath=$root.'/.env';
if (!is_file($envPath)) {
    if ($check) {fwrite(STDERR,"BLOCKED: .env missing (copy .env.example, configure DB_* and APP_URL).\n");exit(2);}
    if (!copy($root.'/.env.example',$envPath)) {fwrite(STDERR,"Cannot initialize .env\n");exit(2);}
    @chmod($envPath,0600);
}
function parseEnv(string $p): array {
    $result=[];
    foreach (file($p, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (!preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/',trim($line),$m)) continue;
        $value=trim($m[2]);
        if (strlen($value)>1 && ($value[0]==='"'||$value[0]==="'") && $value[-1]===$value[0]) $value=substr($value,1,-1);
        $result[$m[1]]=$value;
    }
    return $result;
}
$settings=parseEnv($envPath);
$var=static fn(string $key,string $fallback=''):string => (($sys=getenv($key))!==false && $sys!=='') ? $sys : ($settings[$key]??$fallback);
$database=$var('DB_DATABASE');$username=$var('DB_USERNAME');$password=$var('DB_PASSWORD');
$host=$var('DB_HOST','127.0.0.1');$port=$var('DB_PORT','3306');$url=$var('APP_URL');
if (!preg_match('/^[A-Za-z0-9_]{1,64}$/D',$database) || !$username || !$port || !ctype_digit($port)) {
    fwrite(STDERR,"BLOCKED: Set valid DB_DATABASE (letters/digits/underscore), DB_USERNAME, DB_HOST and DB_PORT.\n");exit(2);
}
if (!preg_match('#^https://[^\s/]+(?:/[^\s]*)?$#D',$url) && !preg_match('#^http://(127\.0\.0\.1|localhost)(?::[0-9]+)?/?$#',$url)) {
    fwrite(STDERR,"BLOCKED: APP_URL must be HTTPS (localhost excepted).\n"); exit(2);
}
$dsn='mysql:host='.$host.';port='.$port.';charset=utf8mb4';
try {
    // Only allowed on CLI; do not expose an unauthenticated install endpoint.
    $db=new PDO($dsn,$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>8]);
    $found=(bool)$db->query('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='.$db->quote($database))->fetchColumn();
    if (!$found && $apply && ($create || $var('DB_AUTO_CREATE')==='true')) {
        $db->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $found=true;
    }
    if (!$found) {fwrite(STDERR,"BLOCKED: MySQL database not present. Supply a MySQL account with CREATE privilege and --apply --create-database, or provision the database once through hPanel.\n");exit(2);}
    $db->exec('USE `'.$database.'`');$db->query('SELECT 1')->fetchColumn();
} catch (PDOException $e) {
    // Never echo SQL errors which may include addresses or user names.
    fwrite(STDERR,"BLOCKED: MySQL connection/CREATE permission failed. Verify DB_* or hosting grant.\n"); exit(2);
}
$writable=[];
foreach (['storage','bootstrap/cache'] as $dir) {
    if (!is_dir($root.'/'.$dir) || !is_writable($root.'/'.$dir)) $writable[]=$dir;
}
if ($writable) {fwrite(STDERR,'BLOCKED: writable directories required: '.implode(', ',$writable)."\n");exit(2);}
if ($check) {fwrite(STDOUT,"PASS: PHP extensions, Composer, database connectivity, HTTPS URL and writable storage are ready. No changes made.\n");exit(0);}
if (!$var('APP_KEY')) {
    $key='base64:'.base64_encode(random_bytes(32));
    $text=file_get_contents($envPath);
    $replaced=preg_replace('/^APP_KEY=.*$/m','APP_KEY='.$key,$text,1,$count);
    if (!$count) $replaced.="\nAPP_KEY=$key\n";
    if (file_put_contents($envPath,$replaced,LOCK_EX)===false) {fwrite(STDERR,"BLOCKED: could not persist APP_KEY\n");exit(2);}
    @chmod($envPath,0600);
}
$run=static function(array $args) use ($root):void {
    $cmd=array_merge([PHP_BINARY,$root.'/artisan'],$args);
    $proc=proc_open($cmd,[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes,$root);
    if (!is_resource($proc) || proc_close($proc)!==0) {fwrite(STDERR,"BLOCKED: artisan step failed: ".$args[0]."\n");exit(3);}
};
$run(['optimize:clear']);
$run(['migrate','--force']);
$run(['db:seed','--class=Database\\Seeders\\BrandSeeder','--force']);
$email=$var('AADI_ADMIN_EMAIL');$pass=getenv('AADI_ADMIN_PASSWORD') ?: ''; 
if ($email && $pass) {
    // Laravel command reads the password only through environment, never CLI args.
    $run(['user:bootstrap-admin',$email,'--password-env=AADI_ADMIN_PASSWORD','--if-missing']);
} else {
    fwrite(STDOUT,"NOTICE: Admin not provisioned. Set AADI_ADMIN_EMAIL and AADI_ADMIN_PASSWORD privately and re-run, or run php artisan user:bootstrap-admin <email> interactively.\n");
}
$run(['config:cache']);$run(['view:cache']);
fwrite(STDOUT,"PASS: database migrated, four brands seeded and Laravel caches built. Run php artisan aadi:doctor for connector/worker readiness.\n");
