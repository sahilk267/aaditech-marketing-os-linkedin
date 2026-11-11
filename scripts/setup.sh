#!/usr/bin/env bash
# Run once in an interactive, private SSH shell. No public installer endpoint.
set -Eeuo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
if [[ -e .env ]]; then echo 'Existing .env preserved. Use scripts/install.sh to upgrade.'; exit 2; fi
if [[ ! -t 0 ]]; then echo 'Interactive terminal required: copy .env.example to .env for unattended deployment.'; exit 2; fi
umask 077
read -r -p 'HTTPS application URL (https://...): ' APP_URL
read -r -p 'MySQL server [127.0.0.1]: ' DB_HOST
DB_HOST="${DB_HOST:-127.0.0.1}"
read -r -p 'Database name: ' DB_DATABASE
read -r -p 'Database username: ' DB_USERNAME
read -r -s -p 'Database password (hidden): ' DB_PASSWORD; printf '\n'
read -r -p 'Initial administrator email: ' AADI_ADMIN_EMAIL
read -r -s -p 'Initial administrator password (minimum 14 characters, hidden): ' AADI_ADMIN_PASSWORD; printf '\n'
if [[ ${#AADI_ADMIN_PASSWORD} -lt 14 || -z $AADI_ADMIN_EMAIL ]]; then
  echo 'A valid admin email and a password of 14+ characters are required.'; exit 2
fi
export APP_URL DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD AADI_ADMIN_EMAIL AADI_ADMIN_PASSWORD
# Never pass passwords as argv or print them; .env is chmod 0600.
php <<'PHP'
<?php
$root=getcwd();$template=file_get_contents($root.'/.env.example');
if ($template===false) {fwrite(STDERR,"Missing .env.example\n");exit(2);}
$vars=['APP_URL','DB_HOST','DB_DATABASE','DB_USERNAME','DB_PASSWORD','AADI_ADMIN_EMAIL'];
foreach ($vars as $name) {
    $value=getenv($name);
    if (!is_string($value) || str_contains($value,"\n") || str_contains($value,"\r") || str_contains($value,"\0")) {
        fwrite(STDERR,"Invalid configuration field: $name\n");exit(2);
    }
    if (in_array($name,['DB_DATABASE','DB_USERNAME'],true) && !preg_match('/^[A-Za-z0-9_@.-]{1,128}$/D',$value)) {
        fwrite(STDERR,"Invalid configuration field: $name\n");exit(2);
    }
    $line=$name.'='.json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $rows=explode("\n",$template);$replaced=false;
    foreach ($rows as &$row) {
        if (str_starts_with($row,$name.'=')) {$row=$line;$replaced=true;break;}
    }
    unset($row);
    if (!$replaced)$rows[]=$line;
    $template=implode("\n",$rows);
}
if (file_put_contents($root.'/.env',$template,LOCK_EX)===false) {fwrite(STDERR,"Cannot write .env\n");exit(2);}
chmod($root.'/.env',0600);
PHP
# Deploy and bootstrap admin in the same process environment (never persist its password).
php scripts/deploy.php --apply --create-database
unset AADI_ADMIN_PASSWORD DB_PASSWORD
php artisan aadi:doctor
