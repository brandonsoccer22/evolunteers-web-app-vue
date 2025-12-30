<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\HasBaseModelFeatures;
use App\Traits\HasTags;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Organization extends Model implements Auditable
{

    use HasBaseModelFeatures, HasFactory, HasTags;

    public function __construct(array $attributes = [])
    {
         // Merge parent and child fillable attributes
        $this->fillable = array_merge($this->baseFillable, $this->fillable);
        parent::__construct($attributes);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

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

            ]
        );
    }


    public function opportunities()
    {
        return $this->belongsToMany(Opportunity::class)->using(OpportunityOrganization::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->wherePivotNull('deleted_at');
    }

    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
    }


    public function files()
    {
        return $this->morphToMany(File::class, 'fileable')
            ->using(\App\Models\Fileable::class)
            ->wherePivotNull('deleted_at');
    }
}
