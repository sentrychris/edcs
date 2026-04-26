<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrontierUser extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
        ];
    }

    public function isTokenExpired(): bool
    {
        return ! $this->token_expires_at || $this->token_expires_at->isPast();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
