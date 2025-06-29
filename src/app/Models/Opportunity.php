<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\HasBaseModelFeatures;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Opportunity extends Model implements Auditable
{

    use HasBaseModelFeatures, HasFactory;

     public function __construct(array $attributes = [])
    {
         // Merge parent and child fillable attributes
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

            ]
        );
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class)->using(OpportunityOrganization::class);
    }

    public function profileImage()
    {
        return $this->belongsTo(File::class, 'profile_image_id');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable')
            ->using(\App\Models\Taggable::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->using(UserOpportunity::class);
    }

    public function files()
    {
        return $this->morphToMany(File::class, 'fileable')
            ->using(\App\Models\Fileable::class);
    }
}
