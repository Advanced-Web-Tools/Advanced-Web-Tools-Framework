<?php

namespace admin;

/** Password hashing shared by administrator login and credential updates. */
final class AdminPassword
{
    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verify(string $password, string $storedHash): bool
    {
        // Accept old SHA-512 hashes only until the next successful login or change.
        if (preg_match('/\A[a-f0-9]{128}\z/', $storedHash) === 1) {
            return hash_equals($storedHash, hash('sha512', $password));
        }

        return password_verify($password, $storedHash);
    }

    public static function needsRehash(string $storedHash): bool
    {
        return password_needs_rehash($storedHash, PASSWORD_DEFAULT);
    }
}
