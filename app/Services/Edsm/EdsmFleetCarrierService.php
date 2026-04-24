<?php

namespace App\Services\Edsm;

use App\Models\FleetCarrier;
use App\Models\System;

class EdsmFleetCarrierService extends EdsmApiService
{
    /**
     * Upsert a fleet carrier keyed on its global market_id. If the carrier
     * has since moved to this system from another, its system_id gets
     * overwritten here.
     *
     * @param  System  $system  - the system the carrier is currently docked in
     * @param  object  $station  - the raw EDSM station payload
     */
    public function upsert(System $system, object $station): ?FleetCarrier
    {
        if (! property_isset($station, 'marketId')) {
            return null;
        }

        return FleetCarrier::updateOrCreate(
            ['market_id' => $station->marketId],
            [
                'system_id' => $system->id,
                'name' => $station->name,

                'distance_to_arrival' => property_isset($station, 'distanceToArrival')
                    ? $station->distanceToArrival
                    : null,

                'allegiance' => property_isset($station, 'allegiance')
                    ? $station->allegiance
                    : null,

                'government' => property_isset($station, 'government')
                    ? $station->government
                    : null,

                'economy' => property_isset($station, 'economy')
                    ? $station->economy
                    : null,

                'second_economy' => property_isset($station, 'secondEconomy')
                    ? $station->secondEconomy
                    : null,

                'has_market' => property_isset($station, 'haveMarket')
                    ? $station->haveMarket
                    : null,

                'has_shipyard' => property_isset($station, 'haveShipyard')
                    ? $station->haveShipyard
                    : null,

                'has_outfitting' => property_isset($station, 'haveOutfitting')
                    ? $station->haveOutfitting
                    : null,

                'other_services' => is_array($station->otherServices)
                    ? implode(',', $station->otherServices)
                    : null,

                'information_last_updated' => property_isset($station, 'updateTime')
                    ? $this->date($station->updateTime->information)
                    : null,

                'market_last_updated' => property_isset($station, 'updateTime')
                    ? $this->date($station->updateTime->market)
                    : null,

                'shipyard_last_updated' => property_isset($station, 'updateTime')
                    ? $this->date($station->updateTime->shipyard)
                    : null,

                'outfitting_last_updated' => property_isset($station, 'updateTime')
                    ? $this->date($station->updateTime->outfitting)
                    : null,
            ]
        );
    }
}
