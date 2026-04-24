<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StationService extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string - the table name
     */
    protected $table = 'station_services';

    /**
     * Guarded attributes that should not be mass assignable.
     *
     * @var array - the guarded attributes
     */
    protected $guarded = [];
}
