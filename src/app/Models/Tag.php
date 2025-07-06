<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasBaseModelFeatures;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;

class Tag extends Model implements Auditable
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

    public function organizations()
    {
        return $this->morphedByMany(Organization::class, 'taggable');
    }

    public function opportunities()
    {
        return $this->morphedByMany(Opportunity::class, 'taggable');
    }
}
