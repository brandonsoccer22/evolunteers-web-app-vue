<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\HasBaseModelFeatures;

class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasBaseModelFeatures;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function __construct(array $attributes = [])
    {
        $this->fillable = array_merge($this->baseFillable, $this->fillable);
        parent::__construct($attributes);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(
            $this->baseCasts,
            [
                'email_verified_at' => 'datetime',
                'password' => 'hashed',
            ]
        );
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class)->using(RoleUser::class);
    }

    public function opportunities()
    {
        return $this->belongsToMany(Opportunity::class)->using(UserOpportunity::class);
    }
}
