<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemInformation extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string - the table name
     */
    protected $table = 'systems_information';

    /**
     * Guarded attributes that should not be mass assignable.
     *
     * @var array - the guarded attributes
     */
    protected $guarded = [];

    /**
     * Get the system this information belongs to.
     *
     * @return BelongsTo - the system this information belongs to
     */
    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }
}
