<?php

namespace App\Models;

use App\Traits\HasQueryFilter;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemStation extends Model
{
    use HasFactory, HasQueryFilter, Sluggable, SluggableScopeHelpers;

    /**
     * The table associated with the model.
     *
     * @var string - the table name
     */
    protected $table = 'systems_stations';

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
     * Get the system these stations belong to.
     *
     * @return BelongsTo - the system these stations belong to
     */
    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    /**
     * Add a query filter scope to filter stations.
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
            'type',
        ], $exact);
    }

    /**
     * Fetch body attribute as an array.
     *
     * @return Attribute - the body attribute
     */
    protected function body(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? json_decode($value) : null
        );
    }

    /**
     * Fetch the other_services attribute as an array.
     *
     * @return Attribute - the other_services attribute
     */
    protected function otherServices(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? explode(',', $value) : null
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
                'source' => ['market_id', 'name'],
                'separator' => '-',
            ],
        ];
    }
}
