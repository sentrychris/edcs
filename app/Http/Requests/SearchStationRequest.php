<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class SearchStationRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string',
            'type' => 'sometimes|string',
            'withSystem' => 'sometimes|integer|max:1',
            'exactSearch' => 'sometimes|integer|max:1',
            'limit' => 'sometimes|int|max:100',
        ];
    }
}
