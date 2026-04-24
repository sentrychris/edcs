<?php

namespace App\Services\Edsm;

use App\Models\System;
use Exception;

class EdsmSystemService extends EdsmApiService
{
    /**
     * Search EDSM for system data by system name and update records if found.
     *
     * @param  string  $name  - the system name
     * @return mixed the created system record or false
     */
    public function updateSystem(string $name): System|bool
    {
        if (strlen($name) > 0 && ctype_digit(substr($name, 0, 1))) {
            // Split the string in case it's a slug prefixed with the id64
            $parts = explode('-', $name, 2);
            $systemName = $parts[1];
        } else {
            $systemName = $name;
        }

        try {
            $response = $this->edsmRequest('systems', 'system', [
                'systemName' => $systemName,
                'showCoordinates' => true,
                'showInformation' => true,
                'showId' => true,
            ]);

            if ($response) {
                return System::updateOrCreate(['id64' => $response->id64], [
                    'id64' => $response->id64,
                    'name' => $response->name,
                    'coords_x' => $response->coords->x,
                    'coords_y' => $response->coords->y,
                    'coords_z' => $response->coords->z,
                    'updated_at' => now(),
                ]);
            }

            $this->logAndAlert('Error updating system: No response from EDSM API for '.$systemName);
        } catch (Exception $e) {
            $this->logAndAlert('Error updating system: '.$systemName.': '.$e->getMessage());
        }

        return false;
    }
}
