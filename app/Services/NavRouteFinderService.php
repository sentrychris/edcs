<?php

namespace App\Services;

use App\Models\System;
use SplMinHeap;

class NavRouteFinderService
{
    /**
     * Find the shortest route (fewest jumps) between two systems using A*.
     *
     * During search, only lightweight node data (id + float coords) is kept in
     * memory. Full System models are loaded in a single query only for the
     * waypoints that make up the final path.
     *
     * @param  float  $jumpRange  - maximum jump distance in light years
     * @param  int  $maxJumps  - abort search if route would exceed this many jumps
     * @return array<System>|null - ordered waypoints, or null if no route found
     */
    public function findRoute(System $from, System $to, float $jumpRange, int $maxJumps = 500): ?array
    {
        if ($from->id === $to->id) {
            return [$from];
        }

        $fromCoords = [
            'x' => (float) $from->coords_x,
            'y' => (float) $from->coords_y,
            'z' => (float) $from->coords_z,
        ];

        $toCoords = [
            'x' => (float) $to->coords_x,
            'y' => (float) $to->coords_y,
            'z' => (float) $to->coords_z,
        ];

        // Lightweight node store: id => [x, y, z]  (no Eloquent models during search)
        $nodeCoords = [$from->id => $fromCoords, $to->id => $toCoords];

        // A* bookkeeping
        $gScore = [$from->id => 0];
        $cameFrom = [$from->id => null];
        $closed = [];

        $heap = new SplMinHeap;
        $heap->insert([$this->distance($fromCoords, $toCoords) / $jumpRange, 0, $from->id]);

        while (! $heap->isEmpty()) {
            [$fScore, $jumps, $currentId] = $heap->extract();

            if (isset($closed[$currentId])) {
                continue;
            }

            $closed[$currentId] = true;

            if ($currentId === $to->id) {
                return $this->loadPathSystems($cameFrom, $to->id);
            }

            if ($jumps >= $maxJumps) {
                continue;
            }

            $currentCoords = $nodeCoords[$currentId];
            $neighbors = System::findNearestForRoute($currentCoords, $jumpRange);

            // Safety: ensure destination is always considered when within range
            $destDist = $this->distance($currentCoords, $toCoords);
            if ($destDist <= $jumpRange && ! $neighbors->contains('id', $to->id)) {
                $neighbors->push((object) ['id' => $to->id, 'coords_x' => $toCoords['x'], 'coords_y' => $toCoords['y'], 'coords_z' => $toCoords['z']]);
            }

            foreach ($neighbors as $neighbor) {
                $neighborId = $neighbor->id;

                if (isset($closed[$neighborId])) {
                    continue;
                }

                $neighborCoords = ['x' => (float) $neighbor->coords_x, 'y' => (float) $neighbor->coords_y, 'z' => (float) $neighbor->coords_z];
                $nodeCoords[$neighborId] ??= $neighborCoords;

                $tentativeG = $jumps + 1;

                if (! isset($gScore[$neighborId]) || $tentativeG < $gScore[$neighborId]) {
                    $gScore[$neighborId] = $tentativeG;
                    $cameFrom[$neighborId] = $currentId;

                    $h = $this->distance($neighborCoords, $toCoords) / $jumpRange;
                    $heap->insert([$tentativeG + $h, $tentativeG, $neighborId]);
                }
            }
        }

        return null;
    }

    /**
     * Reconstruct the path and load full System models in a single query.
     *
     * @param  array<int, int|null>  $cameFrom
     * @return array<System>
     */
    private function loadPathSystems(array $cameFrom, int $targetId): array
    {
        $pathIds = [];
        $currentId = $targetId;

        while ($currentId !== null) {
            $pathIds[] = $currentId;
            $currentId = $cameFrom[$currentId];
        }

        $pathIds = array_reverse($pathIds);

        $systems = System::whereIn('id', $pathIds)->get()->keyBy('id');

        return array_map(fn (int $id) => $systems[$id], $pathIds);
    }

    /**
     * Calculate the Euclidean distance between two coordinate sets.
     *
     * @param  array{x: float, y: float, z: float}  $a
     * @param  array{x: float, y: float, z: float}  $b
     */
    public function distance(array $a, array $b): float
    {
        return sqrt(
            ($a['x'] - $b['x']) ** 2 +
            ($a['y'] - $b['y']) ** 2 +
            ($a['z'] - $b['z']) ** 2
        );
    }
}
