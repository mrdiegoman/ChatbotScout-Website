<?php
/**
 * Central password requirements for every user-controlled password change.
 *
 * Client-side checks are only a usability aid; this class is the authoritative
 * validation used before a password is hashed and stored.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    /**
     * @return string[] Validation errors suitable for display to the user.
     */
    public static function validate(string $password): array
    {
        $errors = [];

        if (mb_strlen($password, 'UTF-8') < self::MIN_LENGTH) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if (!preg_match('/\p{Lu}/u', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }

        if (!preg_match('/\p{Ll}/u', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }

        if (!preg_match('/\p{N}/u', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }

        // A symbol is a non-letter, non-number, non-whitespace character.
        if (!preg_match('/[^\p{L}\p{N}\s]/u', $password)) {
            $errors[] = 'Password must contain at least one special character (for example !, @, or #).';
        }

        return $errors;
    }
}
