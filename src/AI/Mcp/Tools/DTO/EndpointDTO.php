<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\DTO;

class EndpointDTO
{
    /**
     * @param  array<int, string>  $params  Scalar route param names, resolved via $request->get($param).
     * @param  array<string, class-string<\Illuminate\Database\Eloquent\Model>>  $models  Map of MCP argument
     *         name => Eloquent model class, resolved via $class::findOrFail($request->get($param)) to mirror
     *         implicit route-model-binding for controller methods typed against a Model.
     */
    public function __construct(
        public string $controller,
        public string $method,
        public ?string $request = null,
        public array $params = [],
        public array $models = []
    ) {
    }
}
