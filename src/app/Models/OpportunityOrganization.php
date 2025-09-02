<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Relations\Pivot;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\HasBaseModelFeatures;

class OpportunityOrganization extends Pivot implements Auditable
{
    use HasBaseModelFeatures;

    //source: https://github.com/owen-it/laravel-auditing/issues/626
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        //the primary owner of this opportunity
        'is_opportunity_owner'
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
}
