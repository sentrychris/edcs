<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemInformationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'allegiance' => $this->allegiance,
            'government' => $this->government,
            'population' => $this->population,
            'security' => $this->security,
            'economy' => $this->economy,
            'controlling_faction' => [
                'name' => $this->faction,
                'state' => $this->faction_state,
            ],
        ];
    }
}
