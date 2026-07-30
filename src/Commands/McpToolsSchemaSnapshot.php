<?php

namespace Labelgrup\LaravelUtilities\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Labelgrup\LaravelUtilities\AI\Mcp\Resources\ServerToolResolver;
use Labelgrup\LaravelUtilities\AI\Mcp\Resources\ToolsSchemaCatalogBuilder;

class McpToolsSchemaSnapshot extends Command
{
    protected $signature = 'mcp:tools:schema-snapshot {server? : Slug from laravel-utilities.mcp.servers — omit to regenerate all}';

    protected $description = 'Regenerate the tracked JSON snapshot(s) of MCP server tools + schemas, so tool/schema changes are git-diffable per server.';

    public function handle(): int
    {
        $servers = $this->configuredServers();
        $slug = $this->argument('server');

        if (empty($servers)) {
            $this->error('No servers configured in laravel-utilities.mcp.servers.');

            return self::FAILURE;
        }

        if (!$targets = $this->resolveTargets($servers, $slug)) {
            $this->error("Unknown server '{$slug}'. Configured: " . implode(', ', array_keys($servers)));

            return self::FAILURE;
        }

        foreach ($targets as $target_slug => $server_config) {
            $this->writeSnapshot($target_slug, $server_config);
        }

        return self::SUCCESS;
    }

    private function configuredServers(): array
    {
        return config('laravel-utilities.mcp.servers', []);
    }

    /**
     * @param  array<string, mixed>  $catalog
     */
    private function encode(array $catalog): string
    {
        return json_encode(
            $catalog,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) . "\n";
    }

    private function resolveTargets(array $servers, ?string $slug): ?array
    {
        if ($slug === null) {
            return $servers;
        }

        return array_key_exists($slug, $servers) ? [$slug => $servers[$slug]] : null;
    }

    /**
     * @param  array<string, mixed>  $server_config
     */
    private function snapshotPath(string $slug, array $server_config): string
    {
        $directory = rtrim($server_config['schema_snapshot_path'] ?? storage_path('app/mcp-tools-schema-snapshots'), '/');

        return "{$directory}/{$slug}-server-tools-schema-catalog.json";
    }

    /**
     * @param  array<string, mixed>  $server_config
     */
    private function writeSnapshot(string $slug, array $server_config): void
    {
        $path = $this->snapshotPath($slug, $server_config);
        $tool_classes = ServerToolResolver::toolClasses($server_config['class']);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->encode(ToolsSchemaCatalogBuilder::build($tool_classes)));

        $this->info("Snapshot written to {$path}");
    }
}
