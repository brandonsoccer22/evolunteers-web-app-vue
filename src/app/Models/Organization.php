<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\HasBaseModelFeatures;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model implements Auditable
{

    use HasBaseModelFeatures, HasFactory;

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
        'name'
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

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable')
            ->using(\App\Models\Taggable::class);
    }

    public function files()
    {
        return $this->morphToMany(File::class, 'fileable')
            ->using(\App\Models\Fileable::class)
            ->wherePivotNull('deleted_at');
    }
}
