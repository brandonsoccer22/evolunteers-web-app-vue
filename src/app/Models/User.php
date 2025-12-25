<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\HasBaseModelFeatures;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasBaseModelFeatures, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'name',//computed attribute for full name
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
        return $this->belongsToMany(Role::class, 'user_role')
            ->using(RoleUser::class)
            ->wherePivotNull('deleted_at');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->name === $roleName);
    }

    public function hasAnyRole(string ...$roleNames): bool
    {
        if (empty($roleNames)) {
            return false;
        }

        return $this->roles->contains(fn (Role $role) => in_array($role->name, $roleNames, true));
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->wherePivotNull('deleted_at');
    }

    public function opportunities()
    {
        return $this->belongsToMany(Opportunity::class,'user_opportunity')->using(UserOpportunity::class);
    }

    /**
     * Get the user's full name.
     *
     * @return Attribute
     */
    public function name(): Attribute
    {
        return Attribute::make(
            //use this is the computed field is not stored in the database
            //get: fn () => trim("{$this->first_name} {$this->last_name}"),
            set: fn ($value) => throw new \LogicException("The 'name' attribute is computed and cannot be set.")
        );
    }
}
