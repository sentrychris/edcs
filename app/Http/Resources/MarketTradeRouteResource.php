<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketTradeRouteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'commodity' => [
                'name' => $this->resource['commodity_name'],
                'display_name' => config('commodities.'.$this->resource['commodity_name'], $this->resource['commodity_name']),
            ],
            'profit_per_unit' => $this->resource['profit'],
            'buy_from' => new MarketCommodityListingResource($this->resource['buy']),
            'sell_to' => new MarketCommodityListingResource($this->resource['sell']),
        ];
    }
}
