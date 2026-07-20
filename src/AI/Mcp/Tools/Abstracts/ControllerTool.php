<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Abstracts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\ControllerToolInterface;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\ToolErrorResponseBuilderInterface;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\ResolvesToolResponse;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\ResolvesToolSchemas;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class ControllerTool extends Tool implements ControllerToolInterface, ToolErrorResponseBuilderInterface
{
    use ResolvesToolResponse;
    use ResolvesToolSchemas;

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->respond(function () use ($request) {
            $endpoint = $this->endpoint();
            $controller = app($endpoint->controller);
            $route_arguments = [
                ...array_map(fn (string $param) => $request->get($param), $endpoint->params),
                ...array_map(
                    fn (string $model_class, string $param) => $model_class::findOrFail($request->get($param)),
                    $endpoint->models,
                    array_keys($endpoint->models)
                ),
            ];

            return match (true) {
                $endpoint->request !== null => $controller->{$endpoint->method}($this->formRequest($endpoint->request, $request), ...$route_arguments),
                $route_arguments !== [] => $controller->{$endpoint->method}(...$route_arguments),
                default => $controller->{$endpoint->method}(),
            };
        });
    }

    /**
     * Build and validate a controller FormRequest from the MCP tool arguments.
     *
     * @param  class-string<FormRequest>  $class
     */
    protected function formRequest(string $class, Request $request): FormRequest
    {
        $parameters = $request->all();
        $form_request = $class::create('/', 'POST', $parameters, server: ['HTTP_ACCEPT' => 'application/json']);
        $form_request->query->add($parameters);
        $form_request->setContainer(app())->setRedirector(app(Redirector::class));
        $form_request->validateResolved();

        return $form_request;
    }
}
