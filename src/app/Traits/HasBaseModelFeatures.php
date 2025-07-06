<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Relations\Relation;

trait HasBaseModelFeatures
{
  use \OwenIt\Auditing\Auditable;
  use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $baseFillable = [
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $baseCasts = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
    ];

    /**
     * Automatically set created_by, updated_by, and deleted_by fields.
     */
    // This is a base model that can be extended by other models to automatically handle auditing.
    // It uses the OwenIt\Auditing package to track changes and automatically set user IDs
    // for created_by, updated_by, and deleted_by fields based on the authenticated user.
    // The booted method is used to set up model event listeners for creating, updating,
    // and deleting events. When a model is created, it checks if a user is authenticated
    // and sets the created_by field to the authenticated user's ID. Similarly, when a model
    // is updated, it sets the updated_by field to the authenticated user's ID.
    protected static function bootHasBaseModelFeatures()
    {
        static::creating(function ($model) {
            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        static::deleting(function ($model) {
            if (Auth::check() && $model->ha) {
                $model->deleted_by = Auth::id();
            }
            // if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
            //     // This is a hard delete (force delete)
            //     // Place your logic here
            // }
        });
    }

    public static function getMorphAlias($modelClass = null)
    {
        // If no model class is provided, use the current class
        if ($modelClass === null) {
            $modelClass = static::class;
        }

        // Get the morph map and flip it to get [class => alias]
        // If the morph map is empty, return the original class name
        if (!Relation::morphMap()) {
            return $modelClass;
        }

        $map = Relation::morphMap() ?: [];
        // Flip the map to get [class => alias]
        $flipped = array_flip($map);
        return $flipped[$modelClass] ?? $modelClass;
    }
}
