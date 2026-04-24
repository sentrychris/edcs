<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class SearchSystemByInformationRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'population' => 'sometimes|int|min:0',
            'allegiance' => 'sometimes|string',
            'government' => 'sometimes|string',
            'economy' => 'sometimes|string',
            'security' => 'sometimes|string',
            'controlling_faction' => 'sometimes|string',
        ];
    }
}
