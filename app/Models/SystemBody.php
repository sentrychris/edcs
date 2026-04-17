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
use Illuminate\Support\Arr;

class SystemBody extends Model
{
    use HasFactory, HasQueryFilter, Sluggable, SluggableScopeHelpers;

    /**
     * The table associated with the model.
     *
     * @var string - the table name
     */
    protected $table = 'systems_bodies';

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
     * Get the system these bodies belong to.
     *
     * @return BelongsTo - the system these bodies belong to
     */
    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    /**
     * Add a query filter scope to filter system bodies.
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

        if (Arr::exists($options, 'systemn')) {
            $builder->whereHas('system', function ($qb) use ($options, $exact) {
                if (! $exact) {
                    $qb->where('name', 'RLIKE', $options['system']);
                } else {
                    $qb->where('name', $options['system']);
                }
            });
        }

        return $this->buildFilterQuery($builder, $options, [
            'name',
            'type',
        ], $exact);
    }

    /**
     * Fetch the rings attribute as array.
     *
     * @return Attribute - the rings attribute
     */
    protected function rings(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? json_decode($value) : null
        );
    }

    /**
     * Fetch the parents attribute as array.
     *
     * @return Attribute - the parents attribute
     */
    protected function parents(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? json_decode($value) : null
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
}
