<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasBaseModelFeatures;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tag extends Model
{

    use HasBaseModelFeatures, HasFactory;

    protected $fillable = ['name'];

    public function organizations()
    {
        return $this->morphedByMany(Organization::class, 'taggable');
    }

    public function opportunities()
    {
        return $this->morphedByMany(Opportunity::class, 'taggable');
    }
}
