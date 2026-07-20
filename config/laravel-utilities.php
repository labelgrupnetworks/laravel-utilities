<?php

return [
    'mcp' => [
        /**
         * Whether to register MCP-specific behavior (rate limiter, config validation).
         * Defaults to whether laravel/mcp is installed, so consumers without it pay no cost.
         */
        'enabled' => env('LARAVEL_UTILITIES_MCP_ENABLED', class_exists(\Laravel\Mcp\Server::class)),

        /**
         * Optional: class authorizing MCP write-scope access.
         * Must implement \Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\McpScopeAuthorizerInterface.
         */
        'scope_authorizer' => null,

        'errors' => [
            /**
             * Class resolving exceptions to MCP tool error responses.
             * Must implement \Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\ToolErrorResolverInterface.
             */
            'resolver' => \Labelgrup\LaravelUtilities\AI\Mcp\Tools\Errors\DefaultToolErrorResolver::class,

            /**
             * Exception classes whose message is safe to expose to MCP clients.
             */
            'exposed_exceptions' => [],
        ],

        'rate_limit' => [
            // Max MCP requests per minute per client (route: RateLimiter::for('mcp', ...)).
            'per_minute' => env('MCP_RATE_LIMIT_PER_MINUTE', 60),

            /**
             * IPs rate-limited by IP instead of by authenticated user id.
             */
            'whitelist_ips' => [],
        ],
    ],
];
