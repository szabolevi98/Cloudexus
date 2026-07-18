<?php

namespace Cloudexus\Core;

use Cloudexus\Model\Account\UserModel;

class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $user = (new UserModel())->findByUsernameOrEmail($username);

        if (!$user || !$user['is_active']) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        Session::set('user_id', (int) $user['id']);
        Session::set('user_role', $user['role']);
        Session::set('user_name', $user['full_name']);

        (new UserModel())->touchLastLogin((int) $user['id']);

        return true;
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::get('user_id') !== null;
    }

    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    public static function role(): ?string
    {
        return Session::get('user_role');
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }
}
