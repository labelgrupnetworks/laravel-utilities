<?php

namespace Labelgrup\LaravelUtilities\Core\UseCases;

interface UseCaseInterface
{
    public function action();
    public function handle(): UseCaseResponse;
}
