<?php

namespace App\Services\Edsm;

use App\Models\FleetCarrier;
use App\Models\System;
use Exception;

class EdsmSystemStationService extends EdsmApiService
{
    public function __construct(
        private readonly EdsmFleetCarrierService $carriers,
    ) {}

    /**
     * Search EDSM for system stations data by system name and update records if found.
     * Fleet carriers are routed to EdsmFleetCarrierService; regular stations are
     * upserted on the system's stations relation.
     *
     * @param  System  $system  - the system we are searching stations for
     */
    public function updateSystemStations(System $system): void
    {
        $response = $this->edsmRequest('system', 'stations', [
            'systemName' => $system->name,
            'showId' => true,
        ], subkey: 'stations');

        if (! $response || ! property_isset($response, 'stations')) {
            $this->logAndAlert('Error updating system stations: No response from EDSM API for '.$system->name);

            return;
        }

        $seenCarrierMarketIds = [];

        foreach ($response->stations as $station) {
            try {
                if (! $station->type) {
                    $station->type = 'Station';
                }

                if ($station->type === 'Fleet Carrier') {
                    $carrier = $this->carriers->upsert($system, $station);
                    if ($carrier !== null) {
                        $seenCarrierMarketIds[] = $carrier->market_id;
                    }

                    continue;
                }

                $system->stations()->updateOrCreate(
                    [
                        'name' => $station->name,
                        'type' => $station->type,
                    ],
                    [
                        'market_id' => property_isset($station, 'marketId')
                            ? $station->marketId
                            : null,

                        'distance_to_arrival' => property_isset($station, 'distanceToArrival')
                            ? $station->distanceToArrival
                            : null,

                        'body' => property_isset($station, 'body')
                            ? json_encode($station->body)
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

                        'controlling_faction' => property_isset($station, 'controllingFaction')
                            ? $station->controllingFaction->name
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
            } catch (Exception $e) {
                $this->logAndAlert('Error updating system stations: '.$system->name.': '.$e->getMessage());
            }
        }

        // Carriers previously recorded at this system but absent from the
        // current EDSM response have relocated (or been decommissioned) —
        // drop them here so the system's carrier list stays accurate. If
        // they reappear elsewhere, the next /systems/{slug} hit for that
        // system will re-seat them via the market_id-keyed upsert.
        FleetCarrier::where('system_id', $system->id)
            ->whereNotIn('market_id', $seenCarrierMarketIds)
            ->delete();
    }
}
