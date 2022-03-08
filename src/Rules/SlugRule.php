<?php

namespace Labelgrup\LaravelUtilities\Rules;

use Illuminate\Contracts\Validation\Rule;

class SlugRule implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes(
        $attribute, $value
    ): bool {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'Slug is not valid';
    }
}
