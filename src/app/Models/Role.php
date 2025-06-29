<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\HasBaseModelFeatures;

class Role extends Model implements Auditable
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

    public function users()
    {
        return $this->belongsToMany(User::class)->using(RoleUser::class);
    }
}
