<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\HasBaseModelFeatures;
use Illuminate\Support\Facades\Event;

class Taggable extends MorphPivot implements Auditable
{
    use HasBaseModelFeatures;

    protected $table = 'taggables';
    //source: https://github.com/owen-it/laravel-auditing/issues/626
    public $incrementing = true;

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
