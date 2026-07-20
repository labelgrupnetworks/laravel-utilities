<?php

namespace Labelgrup\LaravelUtilities;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Errors\DefaultToolErrorResolver;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\McpScopeAuthorizerInterface;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\ToolErrorResolverInterface;
use Labelgrup\LaravelUtilities\Exceptions\CustomException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class LaravelUtilitiesServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(): void
    {
        $this->publishConfig();
        $this->publishSkills();

        if (config('laravel-utilities.mcp.enabled')) {
            $this->registerMcpRateLimiter();
        }

        $this->commands([
            \Labelgrup\LaravelUtilities\Commands\MakeApiRequest::class,
            \Labelgrup\LaravelUtilities\Commands\MakeUseCase::class
        ]);
    }

    public function register(): void
    {
        $this->registerConfig();

        if (config('laravel-utilities.mcp.enabled')) {
            $this->validateConfig();
        }

        $this->registerHelpers();
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-utilities.php' => config_path('laravel-utilities.php')
        ], config_path('laravel-utilities.php'));
    }

    private function publishSkills(): void
    {
        $skills_source = __DIR__ . '/../skills/Mcp';

        if (!is_dir($skills_source)) {
            return;
        }

        foreach (scandir($skills_source) as $skill) {
            if ($skill === '.' || $skill === '..') {
                continue;
            }

            $this->publishes([
                $skills_source . '/' . $skill => base_path('.claude/skills/' . $skill),
            ], 'laravel-utilities-skills');
        }
    }

    private function registerConfig(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-utilities.php', 'laravel-utilities');
    }

    private function registerHelpers(): void
    {
        $this->app->make('Labelgrup\LaravelUtilities\Helpers\ApiResponse');
        $this->app->make('Labelgrup\LaravelUtilities\Helpers\Time');
        $this->app->make('Labelgrup\LaravelUtilities\Helpers\Zip');
    }

    private function registerMcpRateLimiter(): void
    {
        RateLimiter::for('mcp', function (Request $request) {
            $max_attempts = (int) config('laravel-utilities.mcp.rate_limit.per_minute', 60);
            $whitelist_ips = (array) config('laravel-utilities.mcp.rate_limit.whitelist_ips', []);
            $by = in_array($request->ip(), $whitelist_ips, true) ? $request->ip() : ($request->user()?->id ?? $request->ip());

            return Limit::perMinute($max_attempts)->by($by)->response(function () {
                throw new CustomException(
                    error_code: 'AI-MCP-RATELIMIT-0001',
                    error_message: 'Too many requests.',
                    http_code: Response::HTTP_TOO_MANY_REQUESTS
                );
            });
        });
    }

    private function validateConfig(): void
    {
        $this->validateConfigScopeAuthorizer();
        $this->validateConfigErrorsResolver();
    }

    private function validateConfigErrorsResolver(): void
    {
        $resolver = config('laravel-utilities.mcp.errors.resolver', DefaultToolErrorResolver::class);

        if (!is_a($resolver, ToolErrorResolverInterface::class, true)) {
            throw new RuntimeException("laravel-utilities.mcp.errors.resolver debe implementar {$resolver} " . ToolErrorResolverInterface::class);
        }
    }

    private function validateConfigScopeAuthorizer(): void
    {
        if (!$authorizer = config('laravel-utilities.mcp.scope_authorizer')) {
            return;
        }

        if (!is_a($authorizer, McpScopeAuthorizerInterface::class, true)) {
            throw new RuntimeException("laravel-utilities.mcp.scope_authorizer debe implementar {$authorizer} " . McpScopeAuthorizerInterface::class);
        }
    }
}
