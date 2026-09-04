<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Builds a human-readable validation message straight from the validator's failed rules
 * and parameters, bypassing Laravel's translator entirely. A consuming app may have no
 * translated `validation.php` for its locale (e.g. one where validation copy is translated
 * client-side instead), in which case $exception->errors() returns the raw, untranslated
 * `validation.*` key.
 */
trait FormatsValidationFailure
{
    /**
     * Message per normalized rule name (Str::studly of the rule, as Validator::failed()
     * keys it, e.g. 'min' => 'Min'). ':param' is replaced with the rule's parameters
     * (comma-joined) when present.
     */
    private const RULE_MESSAGES = [
        'Required' => 'is required',
        'String' => 'must be a string',
        'Integer' => 'must be an integer',
        'Numeric' => 'must be a number',
        'Boolean' => 'must be true or false',
        'Array' => 'must be an array',
        'Email' => 'must be a valid email address',
        'Date' => 'must be a valid date',
        'DateFormat' => 'must match the format :param',
        'Min' => 'must be at least :param',
        'Max' => 'must be at most :param',
        'Between' => 'must be between :param',
        'Size' => 'must be exactly :param',
        'In' => 'must be one of: :param',
        'NotIn' => 'must not be one of: :param',
        'Exists' => 'does not match any known value',
        'Distinct' => 'contains a duplicate value',
        'Confirmed' => 'confirmation does not match',
        'Url' => 'must be a valid URL',
        'Uuid' => 'must be a valid UUID',
    ];

    protected function formatValidationFailure(ValidationException $exception): string
    {
        return collect($exception->validator->failed())
            ->map(fn (array $rules, string $field) => "{$field} " . implode(', ', array_map(
                fn (string $rule, array $parameters) => $this->describeFailedRule($rule, $parameters),
                array_keys($rules),
                array_values($rules)
            )))
            ->implode('; ');
    }

    /**
     * @param  array<int, string>  $parameters
     */
    private function describeFailedRule(string $rule, array $parameters): string
    {
        $template = self::RULE_MESSAGES[$rule] ?? "failed the '" . Str::snake($rule) . "' rule";

        return str_replace(':param', implode(', ', $parameters), $template);
    }
}
