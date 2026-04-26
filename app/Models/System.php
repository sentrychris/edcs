<?php

namespace App\Models;

use App\Traits\HasQueryFilter;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class System extends Model
{
    use HasFactory, HasQueryFilter, Sluggable, SluggableScopeHelpers;

    /**
     * The table associated with the model.
     *
     * @var string - the table name
     */
    protected $table = 'systems';

    /**
     * Guarded attributes that should not be mass assignable.
     *
     * @var array - the guarded attributes
     */
    protected $guarded = [];

    /**
     * Whether or not `created_at` and updated_at should be handled automatically.
     *
     * @var bool - whether or not the model should be timestamped
     */
    public $timestamps = false;

    /**
     * Get information related to the system.
     *
     * This will retrieve the information relation for the system which includes stuff such
     * as government, allegiance, economy, population etc.
     *
     * @return HasOne - the information for the system
     */
    public function information(): HasOne
    {
        return $this->hasOne(SystemInformation::class);
    }

    /**
     * Get bodies related to the system.
     *
     * This will retrieve the celestial bodies in the system.
     *
     * @return HasMany - the bodies in the system
     */
    public function bodies(): HasMany
    {
        return $this->hasMany(SystemBody::class);
    }

    /**
     * Get stations related to the system.
     *
     * This will retrieve the stations in the system.
     *
     * @return HasMany - the stations in the system
     */
    public function stations(): HasMany
    {
        return $this->hasMany(SystemStation::class);
    }

    /**
     * Get fleet carriers currently docked in the system.
     *
     * Fleet carriers are mobile — this relation reflects the carriers EDSM
     * last reported as present in this system. A carrier's `system_id` is
     * overwritten when it shows up elsewhere.
     *
     * @return HasMany - the fleet carriers currently in the system
     */
    public function fleetCarriers(): HasMany
    {
        return $this->hasMany(FleetCarrier::class);
    }

    /**
     * Add a query filter scope to filter systems by name.
     *
     * This scope also allows for exact search or `like` search based on the passed options.
     *
     * @param  Builder  $builder  - the query builder
     * @param  array  $options  - the filter options including the search term
     * @param  bool  $exact  - whether or not to use exact search or `like` search
     * @return Builder - the query builder
     */
    public function scopeFilter(Builder $builder, array $options, bool $exact): Builder
    {
        if (! empty($options['search'])) {
            $builder->search($options['search']);
        }

        return $this->buildFilterQuery($builder, $options, [
            'name',
        ], $exact);
    }

    /**
     * Search for systems by distance.
     *
     * Uses the indexed coords_x/y/z columns for a bounding-box pre-filter so
     * MySQL can use the compound index instead of a full table scan. Squared
     * distance then refines to the exact sphere without SQRT/POW on every
     * candidate row.
     *
     * @param  array{x: float, y: float, z: float}  $coords  - the origin coordinates
     * @param  float  $distance  - the search radius in light years
     */
    public static function findNearest(array $coords, float $distance)
    {
        $coords = self::normalizeCoords($coords);
        $distanceSquaredSql = self::distanceSquaredSql();

        $selectRaw = <<<'SQL'
            id,
            id64,
            name,
            coords_x,
            coords_y,
            coords_z,
            slug,
            updated_at,
            %s AS distance_squared
        SQL;

        return self::constrainWithinDistance(
            self::selectRaw(sprintf($selectRaw, $distanceSquaredSql), self::distanceSquaredBindings($coords)),
            $coords,
            $distance,
        )->orderBy('distance_squared');
    }

    /**
     * Find system id64 values within a distance without sorting or projecting
     * display data. This is used by endpoints that only need a membership set.
     *
     * @param  array{x: float, y: float, z: float}  $coords  - the origin coordinates
     * @param  float  $distance  - the search radius in light years
     * @return array<int, int>
     */
    public static function id64sWithinDistance(array $coords, float $distance): array
    {
        $coords = self::normalizeCoords($coords);

        return self::constrainWithinDistance(
            self::query()->select('id64'),
            $coords,
            $distance,
        )->pluck('id64')->all();
    }

    /**
     * Find all systems reachable within a given jump range for route-finding.
     *
     * Returns only the columns needed by the A* algorithm (id, coords_x/y/z),
     * with no LIMIT so the full reachable neighbourhood is returned. Uses the
     * same indexed bounding-box pre-filter as findNearest.
     *
     * @param  array{x: float, y: float, z: float}  $coords  - the origin coordinates
     * @param  float  $jumpRange  - the maximum reachable distance in light years
     * @return Collection<int, object{id: int, coords_x: float, coords_y: float, coords_z: float}>
     */
    public static function findNearestForRoute(array $coords, float $jumpRange): Collection
    {
        $coords = self::normalizeCoords($coords);

        return self::constrainWithinDistance(
            self::select(['id', 'coords_x', 'coords_y', 'coords_z']),
            $coords,
            $jumpRange,
        )->get();
    }

    /**
     * Resolve the virtual distance attribute from the query's squared distance.
     *
     * @return Attribute - calculated distance in light years
     */
    protected function distance(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes): ?float {
                if ($value !== null) {
                    return (float) $value;
                }

                if (! array_key_exists('distance_squared', $attributes)) {
                    return null;
                }

                return sqrt((float) $attributes['distance_squared']);
            }
        );
    }

    /**
     * Configure the URL slug.
     *
     * @return array - the configuration for the slug
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => ['id64', 'name'],
                'separator' => '-',
            ],
        ];
    }

    /**
     * Get the cache key for system related attributes.
     *
     * @param  string  $type  - the attribute type
     * @return string - the cache key
     */
    private function getAttributeCacheKey(string $type)
    {
        return "system_{$this->id64}_{$type}";
    }

    /**
     * @param  array{x: float, y: float, z: float}  $coords
     * @return array{x: float, y: float, z: float}
     */
    private static function normalizeCoords(array $coords): array
    {
        return [
            'x' => (float) $coords['x'],
            'y' => (float) $coords['y'],
            'z' => (float) $coords['z'],
        ];
    }

    /**
     * @param  array{x: float, y: float, z: float}  $coords
     * @return array<int, float>
     */
    private static function distanceSquaredBindings(array $coords): array
    {
        return [
            $coords['x'], $coords['x'],
            $coords['y'], $coords['y'],
            $coords['z'], $coords['z'],
        ];
    }

    private static function distanceSquaredSql(): string
    {
        return <<<'SQL'
            (
                ((coords_x - ?) * (coords_x - ?)) +
                ((coords_y - ?) * (coords_y - ?)) +
                ((coords_z - ?) * (coords_z - ?))
            )
        SQL;
    }

    /**
     * @param  array{x: float, y: float, z: float}  $coords
     */
    private static function constrainWithinDistance(Builder $builder, array $coords, float $distance): Builder
    {
        return $builder
            ->whereBetween('coords_x', [$coords['x'] - $distance, $coords['x'] + $distance])
            ->whereBetween('coords_y', [$coords['y'] - $distance, $coords['y'] + $distance])
            ->whereBetween('coords_z', [$coords['z'] - $distance, $coords['z'] + $distance])
            ->whereRaw(
                self::distanceSquaredSql().' <= ?',
                [...self::distanceSquaredBindings($coords), $distance * $distance],
            );
    }
}
