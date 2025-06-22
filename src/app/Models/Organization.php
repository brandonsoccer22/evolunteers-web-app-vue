<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Organization extends Model implements Auditable
{

    use HasBaseModelFeatures;

    protected $fillable = [
        'name',
    ];

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


    public function opportunities()
    {
        return $this->belongsToMany(Opportunity::class)->using(OpportunityOrganization::class);
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable')
            ->using(\App\Models\Taggable::class);
    }

    public function files()
    {
        return $this->morphToMany(File::class, 'fileable')
            ->using(\App\Models\Fileable::class);
    }
}
