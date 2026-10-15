<?php

/*
 * Set (or reset) a user's password from the server command line.
 * Usage: php tools/set_password.php <username> [account_id]
 * Leave the password empty to generate a random one.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APPLICATION_LOADED', true);
define('DEBUG_MODE', false);
chdir(dirname(__DIR__));
require_once 'inc/db.php';
require_once 'inc/helpers.php';
require_once 'classes/class.mysql.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/set_password.php <username> [account_id]\n");
    exit(1);
}
$username = $argv[1];
$account_id = isset($argv[2]) ? (int) $argv[2] : 1;

$db = new Mysql();
$user = $db->query("SELECT id FROM users WHERE username = " . $db->quote($username) . " AND for_account = " . $account_id);
if ($user === false || $user->num_rows === 0) {
    fwrite(STDERR, "No user '$username' in account $account_id.\n");
    exit(1);
}
$user_id = (int) $user->fetch_object()->id;

$tty = function_exists('posix_isatty') && posix_isatty(STDIN);
echo "New password for $username (empty = generate one): ";
if ($tty) {
    system('stty -echo');
}
$password = trim((string) fgets(STDIN));
if ($tty) {
    system('stty echo');
    echo "\n";
}

$generated = false;
if ($password === '') {
    $password = random_string('abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789', 16);
    $generated = true;
}
if (strlen($password) < 10) {
    fwrite(STDERR, "Password must be at least 10 characters.\n");
    exit(1);
}

$db->query("UPDATE users SET password = " . $db->quote(hash_pass($password)) . ", remember_token = NULL WHERE id = " . $user_id);
echo $generated ? "Password set to: $password\n" : "Password updated.\n";
