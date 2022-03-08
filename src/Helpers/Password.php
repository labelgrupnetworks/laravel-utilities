<?php

namespace Labelgrup\LaravelUtilities\Helpers;

class Password
{
    /**
     * @throws \Exception
     */
    public static function rule(int $min_size = 8): \Illuminate\Validation\Rules\Password
    {
        if ( $min_size < 3 ) {
            throw new \Exception(__('Require a min. 3 characters size'));
        }

        return \Illuminate\Validation\Rules\Password::min($min_size)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->uncompromised();
    }
}
