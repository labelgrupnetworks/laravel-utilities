<?php

namespace Labelgrup\LaravelUtilities\Test\Unit\AI\Mcp\Tools\Resolvers;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\FormatsValidationFailure;
use PHPUnit\Framework\TestCase;

enum FormatsValidationFailureTestStatus: string
{
    case Active = 'active';
}

class FormatsValidationFailureTest extends TestCase
{
    use FormatsValidationFailure;

    public function test_closure_rule_uses_the_fail_message(): void
    {
        $message = $this->format(
            ['name' => 'x'],
            [
                'name' => [
                    function (string $attribute, mixed $value, \Closure $fail) {
                        $fail('literal text');
                    },
                ],
            ]
        );

        $this->assertSame('name literal text', $message);
    }

    public function test_native_rule_keeps_the_rule_message(): void
    {
        $message = $this->format(['age' => 1], ['age' => 'min:5']);

        $this->assertSame('age must be at least 5', $message);
    }

    public function test_unknown_rule_with_untranslated_message_uses_generic_fallback(): void
    {
        $message = $this->format(['name' => 'x'], ['name' => 'ends_with:z']);

        $this->assertSame("name failed the 'ends_with' rule", $message);
    }

    public function test_invalid_enum_uses_enum_message(): void
    {
        $message = $this->format(
            ['status' => 'nope'],
            ['status' => [Rule::enum(FormatsValidationFailureTestStatus::class)]]
        );

        $this->assertSame('status must be one of the allowed values', $message);
    }

    public function test_fields_are_separated_by_semicolon(): void
    {
        $message = $this->format(['a' => null, 'b' => null], ['a' => 'required', 'b' => 'required']);

        $this->assertSame('a is required; b is required', $message);
    }

    private function format(array $data, array $rules): string
    {
        $translator = new Translator(new ArrayLoader, 'en');
        $factory = new Factory($translator);

        try {
            $factory->make($data, $rules)->validate();
        } catch (ValidationException $exception) {
            return $this->formatValidationFailure($exception);
        }

        $this->fail('Validation was expected to fail.');
    }
}
