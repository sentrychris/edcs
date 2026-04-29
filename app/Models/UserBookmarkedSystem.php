<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBookmarkedSystem extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string - the table name
     */
    protected $table = 'users_bookmarked_systems';

    /**
     * Guarded attributes that should not be mass assignable.
     *
     * @var array - the guarded attributes
     */
    protected $guarded = [];

    /**
     * Single record points to single user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Single record points to single system
     */
    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }
}
