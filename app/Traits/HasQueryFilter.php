<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

trait HasQueryFilter
{
    /**
     * Builds a filter query based on model attributes.
     *
     * @param  Builder  $builder  The query builder
     * @param  array  $options  The filter option
     * @param  string|array  $attribute  The model attribute(s) to filter
     * @return Builder $builder The query builder
     */
    public function buildFilterQuery(Builder $builder, array $options, string|array $attributes, bool $exact): Builder
    {
        foreach (Arr::wrap($attributes) as $attr) {
            if (! Arr::exists($options, $attr) || ! $options[$attr]) {
                continue;
            }

            $value = explode(',', $options[$attr]);

            if ($exact) {
                $builder->whereIn($attr, $value);
            } elseif (count($value) === 1) {
                $builder->where($attr, 'LIKE', "{$value[0]}%");
            } else {
                $builder->where($attr, 'RLIKE', $value);
            }
        }

        return $builder;
    }
}
