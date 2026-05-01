<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class SearchSystemBodyRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'system' => 'sometimes|string',
            'name' => 'sometimes|string',
            'type' => 'sometimes|string',
            'withSystem' => 'sometimes|int|max:1',
            'withStations' => 'sometimes|int|max:1',
            'withBodies' => 'sometimes|int|max:1',
        ];
    }
}
