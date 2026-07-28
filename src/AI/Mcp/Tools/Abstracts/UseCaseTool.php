<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Abstracts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Attributes\RequestClass;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\ToolErrorResponseBuilderInterface;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\UseCaseToolInterface;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\ResolvesToolResponse;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\ResolvesToolSchemas;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;

abstract class UseCaseTool extends Tool implements ToolErrorResponseBuilderInterface, UseCaseToolInterface
{
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

    public function resolveRequestClass(Request $request): Request|FormRequest
    {
        $request_class = $this->requestClassAttribute();

        if (!$request_class) {
            return $request;
        }

        $class = $request_class->class;

        $form_request = $class::create('/', 'GET', $request->all());
        $form_request->setContainer(app())->setRedirector(app(Redirector::class));
        $form_request->validateResolved();

        return $form_request;
    }

    private function requestClassAttribute(): ?RequestClass
    {
        $attribute = (new ReflectionClass($this))->getAttributes(RequestClass::class)[0] ?? null;

        return $attribute?->newInstance();
    }

    private function validateUsingRequestClass(Request $request): void
    {
        $request_class = $this->requestClassAttribute();

        if (!$request_class) {
            return;
        }

        $class = $request_class->class;

        $request->merge($request->validate((new $class)->rules()));
    }
}
