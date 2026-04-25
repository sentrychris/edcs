<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class SearchCommodityRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'commodity' => ['required', 'string'],
            'near_system' => ['sometimes', 'string', 'exists:systems,slug'],
            'ly' => ['sometimes', 'numeric', 'min:1', 'max:5000'],
            'min_stock' => ['sometimes', 'integer', 'min:0'],
            'min_demand' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
