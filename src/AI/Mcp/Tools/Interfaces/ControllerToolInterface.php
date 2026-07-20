<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces;

use Labelgrup\LaravelUtilities\AI\Mcp\Tools\DTO\EndpointDTO;

interface ControllerToolInterface
{
    public function endpoint(): EndpointDTO;
}
