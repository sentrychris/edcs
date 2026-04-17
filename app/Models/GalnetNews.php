<?php

namespace App\Models;

use App\Traits\HasQueryFilter;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalnetNews extends Model
{
    use HasFactory, HasQueryFilter, Sluggable, SluggableScopeHelpers;

    /**
     * The table associated with the model.
     *
     * @var string - the table name
     */
    protected $table = 'galnet_news';

    /**
     * Guarded attributes that should not be mass assignable.
     *
     * @var array - the guarded attributes
     */
    protected $guarded = [];

    /**
     *  Whether or not `created_at` and updated_at should be handled automatically.
     *
     * @var bool - whether or not the model should be timestamped
     */
    public $timestamps = false;

    /**
     * Boot method for the model.
     *
     * Adds a global scope to automatically order the results by the `order_added` column.
     */
    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('order_added', 'desc');
        });
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
                'source' => ['uploaded_at', 'title'],
                'separator' => '-',
            ],
        ];
    }
}
