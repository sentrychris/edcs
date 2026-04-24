<?php

namespace App\Services\Edsm;

use App\Models\System;
use Exception;

class EdsmSystemInformationService extends EdsmApiService
{
    /**
     * Search EDSM for system information data by system name and update records if found.
     *
     * @param  System  $system  - the system we are searching information for
     */
    public function updateSystemInformation(System $system): void
    {
        $response = $this->edsmRequest('systems', 'system', [
            'systemName' => $system->name,
            'showInformation' => true,
        ]);

        if (! $response || ! property_isset($response, 'information')) {
            $this->logAndAlert('Error updating system information: No response from EDSM API for '.$system->name);

            return;
        }

        try {
            $information = $response->information;

            $system->information()->updateOrCreate([
                'allegiance' => property_isset($information, 'allegiance')
                    ? $information->allegiance
                    : null,

                'government' => property_isset($information, 'government')
                    ? $information->government
                    : null,

                'faction' => property_isset($information, 'faction')
                    ? $information->faction
                    : null,

                'faction_state' => property_isset($information, 'factionState')
                    ? $information->factionState
                    : null,

                'economy' => property_isset($information, 'economy')
                    ? $information->economy
                    : null,

                'population' => property_isset($information, 'population')
                    ? $information->population
                    : 0,

                'security' => property_isset($information, 'security')
                    ? $information->security
                    : null,
            ]);
        } catch (Exception $e) {
            $this->logAndAlert('Error updating system information: '.$system->name.': '.$e->getMessage());
        }
    }
}
