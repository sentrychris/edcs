<?php

namespace App\Services\Edsm;

use App\Models\System;
use App\Models\SystemBody;
use Exception;
use Illuminate\Support\Str;

class EdsmSystemBodyService extends EdsmApiService
{
    /**
     * Search EDSM for system bodies data by system name and update records if found.
     *
     * @param  System  $system  - the system we are searching bodies for
     */
    public function updateSystemBodies(System $system): void
    {
        $response = $this->edsmRequest('system', 'bodies', [
            'systemName' => $system->name,
        ]);

        if (! $response) {
            $this->logAndAlert('Error updating system bodies: No response from EDSM API for '.$system->name);

            return;
        }

        $bodies = [];
        if (property_isset($response, 'bodies')) {
            $bodies = $response->bodies;
        } elseif (is_array($response) && isset($response['bodies'])) {
            $bodies = $response['bodies'];
        }

        $system->body_count = property_isset($response, 'bodyCount')
            ? $response->bodyCount
            : null;

        $system->save();

        if (! $bodies) {
            return;
        }

        $records = [];

        foreach ($bodies as $body) {
            try {
                $id = property_isset($body, 'id64')
                    ? $body->id64
                    : random_int(100000000, 999999999);

                $isMainStar = property_isset($body, 'isMainStar') ? $body->isMainStar : false;

                if (property_isset($body, 'bodyId')) {
                    $bodyId = $body->bodyId;
                } elseif ($isMainStar) {
                    $bodyId = 0;
                } else {
                    $bodyId = random_int(100000000, 999999999);
                }

                $records[] = [
                    'id64' => $id,
                    'body_id' => $bodyId,
                    'system_id' => $system->id,
                    'name' => $body->name,
                    'type' => $body->type,
                    'sub_type' => $body->subType,
                    'slug' => Str::slug($id.' '.$body->name),
                    'discovered_by' => property_isset($body, 'discovery') ? $body->discovery->commander : null,
                    'discovered_at' => property_isset($body, 'discovery') ? $body->discovery->date : null,
                    'distance_to_arrival' => property_isset($body, 'distanceToArrival') ? $body->distanceToArrival : null,
                    'is_main_star' => property_isset($body, 'isMainStar') ? $body->isMainStar : false,
                    'is_scoopable' => property_isset($body, 'isScoopable') ? $body->isScoopable : false,
                    'spectral_class' => property_isset($body, 'spectralClass') ? $body->spectralClass : null,
                    'luminosity' => property_isset($body, 'luminosity') ? $body->luminosity : null,
                    'solar_masses' => property_isset($body, 'solarMasses') ? $body->solarMasses : null,
                    'solar_radius' => property_isset($body, 'solarRadius') ? $body->solarRadius : null,
                    'absolute_magnitude' => property_isset($body, 'absoluteMagnitude') ? $body->absoluteMagnitude : null,
                    'surface_temp' => property_isset($body, 'surfaceTemperature') ? $body->surfaceTemperature : null,
                    'radius' => property_isset($body, 'radius') ? $body->radius : null,
                    'gravity' => property_isset($body, 'gravity') ? $body->gravity : null,
                    'earth_masses' => property_isset($body, 'earthMasses') ? $body->earthMasses : null,
                    'atmosphere_type' => property_isset($body, 'atmosphereType') ? $body->atmosphereType : null,
                    'volcanism_type' => property_isset($body, 'volcanismType') ? $body->volcanismType : null,
                    'terraforming_state' => property_isset($body, 'terraformingState') ? $body->terraformingState : null,
                    'is_landable' => property_isset($body, 'isLandable') ? $body->isLandable : false,
                    'orbital_period' => property_isset($body, 'orbitalPeriod') ? $body->orbitalPeriod : null,
                    'orbital_eccentricity' => property_isset($body, 'orbitalEccentricity') ? $body->orbitalEccentricity : null,
                    'orbital_inclination' => property_isset($body, 'orbitalInclination') ? $body->orbitalInclination : null,
                    'arg_of_periapsis' => property_isset($body, 'argOfPeriapsis') ? $body->argOfPeriapsis : null,
                    'rotational_period' => property_isset($body, 'rotationalPeriod') ? $body->rotationalPeriod : null,
                    'is_tidally_locked' => property_isset($body, 'rotationalPeriodTidallyLocked') ? $body->rotationalPeriodTidallyLocked : false,
                    'semi_major_axis' => property_isset($body, 'semiMajorAxis') ? $body->semiMajorAxis : null,
                    'axial_tilt' => property_isset($body, 'axialTilt') ? $body->axialTilt : null,
                    'rings' => property_isset($body, 'rings') ? json_encode($body->rings) : null,
                    'parents' => property_isset($body, 'parents') ? json_encode($body->parents) : null,
                ];
            } catch (Exception $e) {
                $this->logAndAlert('Error updating system bodies: '.$system->name.': '.$e->getMessage());
            }
        }

        if (empty($records)) {
            return;
        }

        try {
            SystemBody::upsert($records, ['id64'], [
                'body_id', 'name', 'type', 'sub_type', 'discovered_by', 'discovered_at',
                'distance_to_arrival', 'is_main_star', 'is_scoopable', 'spectral_class',
                'luminosity', 'solar_masses', 'solar_radius', 'absolute_magnitude',
                'surface_temp', 'radius', 'gravity', 'earth_masses', 'atmosphere_type',
                'volcanism_type', 'terraforming_state', 'is_landable', 'orbital_period',
                'orbital_eccentricity', 'orbital_inclination', 'arg_of_periapsis',
                'rotational_period', 'is_tidally_locked', 'semi_major_axis', 'axial_tilt',
                'rings', 'parents',
            ]);
        } catch (Exception $e) {
            $this->logAndAlert('Error upserting system bodies: '.$system->name.': '.$e->getMessage());
        }
    }
}
