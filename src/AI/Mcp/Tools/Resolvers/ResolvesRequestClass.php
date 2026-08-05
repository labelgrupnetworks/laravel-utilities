<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Attributes\RequestClass;
use Laravel\Mcp\Request;
use ReflectionClass;

/**
 * Shared #[RequestClass] mechanics: an automatic pass (validateUsingRequestClass())
 * plus an opt-in, fully-bound FormRequest instance (resolveRequestClass()) for typed
 * getters/accessors.
 */
trait ResolvesRequestClass
{
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
