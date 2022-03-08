<?php

namespace Labelgrup\LaravelUtilities;

class LaravelUtilitiesServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(): void
    {
        $this->commands([
            \Labelgrup\LaravelUtilities\Commands\MakeUseCase::class
        ]);
    }

    public function register(): void
    {
        $this->registerHelpers();
    }

    private function registerHelpers(): void
    {
        $this->app->make('Labelgrup\LaravelUtilities\Helpers\ApiResponse');
        $this->app->make('Labelgrup\LaravelUtilities\Helpers\Time');
        $this->app->make('Labelgrup\LaravelUtilities\Helpers\Zip');
    }
}
