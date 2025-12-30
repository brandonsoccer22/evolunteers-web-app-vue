<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\HasBaseModelFeatures;
use App\Traits\HasTags;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Builder;

class Opportunity extends Model implements Auditable
{

    use HasBaseModelFeatures, HasFactory, HasTags, Searchable;

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
        'url',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
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

    public function organizations()
    {
        return $this->belongsToMany(Organization::class)->using(OpportunityOrganization::class)->wherePivotNull('deleted_at');
    }

    public function profileImage()
    {
        return $this->belongsTo(File::class, 'profile_image_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class,'user_opportunity')->using(UserOpportunity::class);
    }

    public function files()
    {
        return $this->morphToMany(File::class, 'fileable')
            ->using(\App\Models\Fileable::class)
            ->wherePivotNull('deleted_at');
    }

    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('organizations', function ($orgQuery) use ($user) {
            $orgQuery->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', $user->id));
        });
    }

    public function searchableAs(): string
    {
        $prefix = config('scout.prefix', '');
        $collection = 'opportunities';

        return $prefix ? "{$prefix}_{$collection}" : $collection;
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['organizations', 'tags']);

        return [
            'id' => (string) $this->getKey(),
            'name' => $this->name,
            'description' => $this->description,
            'start_date_ts' => $this->start_date
                ? Carbon::parse($this->start_date, 'UTC')->startOfDay()->timestamp
                : null,
            'created_at_ts' => $this->created_at->timestamp,
            'organization_names' => $this->organizations?->pluck('name')->filter()->values()->all() ?? [],
            'tag_names' => $this->tags?->pluck('name')->filter()->values()->all() ?? [],
        ];
    }

    public function typesenseCollectionSchema(): array
    {
        return [
            'name' => $this->searchableAs(),
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'description', 'type' => 'string', 'optional' => true],
                ['name' => 'start_date_ts', 'type' => 'int64', 'optional' => true],
                ['name' => 'created_at_ts', 'type' => 'int64', 'optional' => false],
                ['name' => 'organization_names', 'type' => 'string[]', 'optional' => true],
                ['name' => 'tag_names', 'type' => 'string[]', 'optional' => true],
                ['name' => '__soft_deleted','type' => 'int32', 'optional' => true],
            ],
            'default_sorting_field' => 'created_at_ts',
        ];
    }
}
