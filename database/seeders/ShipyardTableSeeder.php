<?php

namespace Database\Seeders;

use App\Models\Shipyard;
use Exception;
use Illuminate\Database\Seeder;

class ShipyardTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $file = storage_path('FDevIDs/shipyard.csv');
        if (! file_exists($file)) {
            throw new Exception('shipyard.csv not found, ensure FDevIDs submodule is present.');
        }

        $handle = fopen($file, 'r');

        fgetcsv($handle); // Skip the first line containing headers
        while (($data = fgetcsv($handle)) !== false) {
            $fdev_id = $data[0];
            $name = str_replace('_', ' ', $data[1]);

            if ($name === 'BelugaLiner') {
                $name = 'Beluga Liner';
            }
            if ($name === 'CobraMkIII') {
                $name = 'Cobra MkIII';
            }
            if ($name === 'CobraMkIV') {
                $name = 'Cobra MkIV';
            }
            if ($name === 'FerDeLance') {
                $name = 'Fer-de-Lance';
            }
            if ($name === 'DiamondBackXL') {
                $name = 'Diamondback Explorer';
            }
            if ($name === 'DiamondBack') {
                $name = 'Diamondback Scout';
            }

            Shipyard::updateOrCreate(
                ['fdev_id' => $fdev_id],
                ['name' => $name, 'image' => "ships/{$fdev_id}.jpg"]
            );
        }
    }
}
