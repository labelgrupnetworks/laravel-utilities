<?php

namespace Labelgrup\LaravelUtilities\Core\Requests;

use Illuminate\Http\Exceptions\HttpResponseException;
use Labelgrup\LaravelUtilities\Helpers\ApiResponse;

abstract class ApiRequest extends \Illuminate\Foundation\Http\FormRequest
{
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(ApiResponse::fail(__('Validation Error'), $validator->errors()->toArray()));
        }

        parent::failedValidation($validator);
    }
}
