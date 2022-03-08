<?php

namespace Labelgrup\LaravelUtilities\Core\Commands;

abstract class GeneratorCommand extends \Illuminate\Console\GeneratorCommand
{
    protected function resolveStubPath(string $stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }
}
