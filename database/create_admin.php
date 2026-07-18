<?php

/**
 * Creates the initial admin user (or resets its password if it already exists).
 *
 * Usage: php database/create_admin.php [username] [password]
 * Defaults: admin / admin123
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Cloudexus\Core\Config;
use Cloudexus\Model\Account\UserModel;

Config::load(dirname(__DIR__) . '/config/config.ini');

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? 'admin123';

$users = new UserModel();
$existing = $users->findByUsernameOrEmail($username);

if ($existing) {
    $users->update((int) $existing['id'], [
        'username' => $existing['username'],
        'email' => $existing['email'],
        'full_name' => $existing['full_name'],
        'role' => 'admin',
        'is_active' => 1,
        'password' => $password,
    ]);
    echo "User '$username' already existed — password reset, role set to admin.\n";
} else {
    $users->create([
        'username' => $username,
        'email' => $username . '@cloudexus.local',
        'password' => $password,
        'full_name' => ucfirst($username),
        'role' => 'admin',
        'is_active' => 1,
    ]);
    echo "Admin user '$username' created.\n";
}
