<?php

namespace Labelgrup\LaravelUtilities\Commands;

class MakeApiRequest extends \Labelgrup\LaravelUtilities\Core\Commands\GeneratorCommand
{
    protected $signature = 'make:api-request {name}';

    protected $description = 'Create a API Request';

    protected $type = 'ApiRequest';

    protected function getStub(): string
    {
        return $this->resolveStubPath('/../../stubs/api.request.stub');
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Http\Requests\Api';
    }

    protected function buildClass($name): string
    {
        $replace = [
            '$NAMESPACE$' => $this->getNamespace($name),
            '$CLASS_NAME$' => str_replace($this->getNamespace($name) . '\\', '', $name)
        ];

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }
}
