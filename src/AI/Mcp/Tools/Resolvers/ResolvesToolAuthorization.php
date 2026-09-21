<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers;

use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Attributes\Authorize;
use ReflectionClass;

/**
 * Runs every #[Authorize] declared on the Tool class or any of its parents (AND
 * combined — the first authorizer to throw stops execution before the Tool runs).
 */
trait ResolvesToolAuthorization
{
    protected function authorizeUsingAttributes(): void
    {
        foreach ($this->authorizeAttributes() as $attribute) {
            app($attribute->authorizer)->authorize($this, $attribute->parameters);
        }
    }

    /**
     * @return Authorize[]
     */
    private function authorizeAttributes(): array
    {
        $attributes = [];
        $reflection = new ReflectionClass($this);

        do {
            foreach ($reflection->getAttributes(Authorize::class) as $attribute) {
                $attributes[] = $attribute->newInstance();
            }
        } while ($reflection = $reflection->getParentClass());

        return $attributes;
    }
}
