<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketCommodityListingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $station = $this->resource['station'];
        $system = $this->resource['system'];
        $commodity = $this->resource['commodity'];

        return [
            'station' => [
                'id' => $station->id,
                'name' => $station->name,
                'type' => $station->type,
                'slug' => $station->slug,
                'distance_to_arrival' => $station->distance_to_arrival,
            ],
            'system' => [
                'id64' => $system->id64,
                'name' => $system->name,
                'slug' => $system->slug,
                'coords' => ['x' => $system->coords_x, 'y' => $system->coords_y, 'z' => $system->coords_z],
            ],
            'buy_price' => (int) ($commodity['buyPrice'] ?? 0),
            'sell_price' => (int) ($commodity['sellPrice'] ?? 0),
            'mean_price' => (int) ($commodity['meanPrice'] ?? 0),
            'stock' => (int) ($commodity['stock'] ?? 0),
            'demand' => (int) ($commodity['demand'] ?? 0),
            'last_updated' => $this->resource['last_updated'],
        ];
    }
}
