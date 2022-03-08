<?php

namespace Labelgrup\LaravelUtilities\Commands;

class MakeUseCase extends \Labelgrup\LaravelUtilities\Core\Commands\GeneratorCommand
{
    protected $signature = 'make:use-case {name}';

    protected $description = 'Create a new UseCase class';

    protected $type = 'UseCase';

    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/use.case.stub');
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\UseCases';
    }

    protected function buildClass($name): string
    {
        $replace = [
            '$NAMESPACE$' => $this->getNamespace($name),
            '$CLASS_NAME$' => str_replace($this->getNamespace($name).'\\', '', $name)
        ];

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }
}
