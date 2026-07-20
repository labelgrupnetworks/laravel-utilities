<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Traits;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ObjectType;

trait PaginationTrait
{
    public static function paginationOutputSchema(
        JsonSchema $schema,
        ObjectType $item_schema,
        ?string $items_description = null,
        ?string $total_items_description = null
    ): array {
        return [
            'items' => $schema->array()->items($item_schema)->description($items_description ?? 'List of items.'),
            'data' => $schema->object([
                'current_page' => $schema->integer()->description('Current page number.'),
                'last_page' => $schema->integer()->description('Last page number.'),
                'total_items' => $schema->integer()->description($total_items_description ?? 'Total number of items.')
            ])->description('Pagination metadata.')
        ];
    }
}
