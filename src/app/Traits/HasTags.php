<?php

namespace App\Traits;
use App\Models\Tag;
use App\Models\Taggable;


trait HasTags
{
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable')
            ->using(Taggable::class)
            ->wherePivotNull('deleted_at');
    }

     public function attachTag(Tag $tag, $userId = null)
    {
        $tagId = $tag->id;
        $pivot = \App\Models\Taggable::withTrashed()
            ->where('tag_id', $tagId)
            ->where('taggable_id', $this->id)
            ->where('taggable_type', static::getMorphAlias())
            ->first();

        if ($pivot) {
            $pivot->deleted_at = null;
            $pivot->created_by = $userId ?? request()->user()?->id;
            $pivot->created_at = now();
            $pivot->save();

            // Manually fire the restored event for auditing
            $pivot->fireModelEvent('restored', false);
        } else {
            $this->tags()->attach($tagId, [
                'created_by' => $userId ?? request()->user()?->id,
                'created_at' => now(),
            ]);
        }
    }
}
