<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array - the mass assignable attributes
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array - the hidden attributes
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Eager load the commander with the user.
     *
     * @var array - the eager loaded relation
     */
    protected $with = ['frontierUser', 'commander'];

    /**
     * The attributes that should be cast.
     *
     * @var array - the casted attributes
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function frontierUser(): HasOne
    {
        return $this->hasOne(FrontierUser::class);
    }

    /**
     * Get the commander that belongs to the user.
     *
     * @return HasOne - the commander that belongs to the user
     */
    public function commander(): HasOne
    {
        return $this->hasOne(Commander::class);
    }

    /**
     * Get the bookmarked systems belonging to the user
     */
    public function bookmarkedSystems(): BelongsToMany
    {
        return $this->belongsToMany(System::class, 'users_bookmarked_systems')
            ->withTimestamps();
    }

    /**
     * Determine if the user has bookmarked the given system.
     *
     * Memoizes the bookmarked system IDs on the user instance so that callers
     * iterating over a collection of systems (e.g. SystemResource::collection)
     * do not trigger one query per row.
     */
    public function hasBookmarked(System $system): bool
    {
        return $this->bookmarkedSystemIds()->contains($system->id);
    }

    /**
     * @return Collection<int, int>
     */
    private function bookmarkedSystemIds(): Collection
    {
        return once(fn () => $this->bookmarkedSystems()->pluck('systems.id'));
    }
}
