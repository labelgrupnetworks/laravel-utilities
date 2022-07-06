<?php

namespace Labelgrup\LaravelUtilities\Helpers;

class Password
{
    private const PASSWORD_LENGTH = 12;
    private const MAX_RETRIES = 5;

    /**
     * @param int $min_size
     * @return \Illuminate\Validation\Rules\Password
     * @throws \Exception
     */
    public static function rule(int $min_size = 8): \Illuminate\Validation\Rules\Password
    {
        if ( $min_size < self::PASSWORD_LENGTH ) {
            throw new \Exception(__("Password doesn't meet minimal length requirement"));
        }

        return \Illuminate\Validation\Rules\Password::min($min_size)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->uncompromised();
    }

    /**
     * @param int $length
     * @param int $max_retries
     * @return string
     * @throws \Exception
     */
    public static function generateSecurePassword(
        int $length = self::PASSWORD_LENGTH,
        int $max_retries = self::MAX_RETRIES
    ): string {
        $password = '';
        $leaked = true;
        $retries = 0;

        while ($leaked && $retries < $max_retries) {
            $password = substr(
                str_shuffle('abcdefghjklmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ1234567890!$%^&!$%^&')
                . \Illuminate\Support\Str::random(self::PASSWORD_LENGTH),
                0,
                $length
            );

            $leaked = self::isLeakedPassword($password);
            $retries++;
        }

        if ($retries >= self::MAX_RETRIES) {
            throw new \Exception(__("Couldn't generate a random password not leaked"));
        }

        return $password;
    }

    /**
     * @param string $password
     * @return bool
     */
    public static function isLeakedPassword(string $password): bool
    {
        $verifier = new \Illuminate\Validation\NotPwnedVerifier((new \Illuminate\Http\Client\Factory()));
        return !$verifier->verify([
            'value' => $password,
            'threshold' => 0
        ]);
    }
}
