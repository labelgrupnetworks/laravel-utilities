<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Abstracts;

use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\ToolErrorResponseBuilderInterface;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\UseCaseToolInterface;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\NormalizesNullableSchemaTypes;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\ResolvesRequestClass;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\ResolvesToolName;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\ResolvesToolResponse;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\ResolvesToolSchemas;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class UseCaseTool extends Tool implements ToolErrorResponseBuilderInterface, UseCaseToolInterface
{
    use NormalizesNullableSchemaTypes;
    use ResolvesRequestClass;
    use ResolvesToolName;
    use ResolvesToolResponse;
    use ResolvesToolSchemas;

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->respond(function () use ($request) {
            $this->validateUsingRequestClass($request);

            $use_case_dto = $this->useCase($request);

            return $use_case_dto->use_case->handle()->responseToApi(
                $use_case_dto->response_simplified,
                $use_case_dto->resource_class
            );
        });
    }
}
