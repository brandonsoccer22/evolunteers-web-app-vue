<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\HasBaseModelFeatures;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class File extends Model implements Auditable
{
    use HasBaseModelFeatures, HasFactory;

    protected $fillable = [
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'user_id',
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

    // Automatically delete file from disk when deleted from DB
    protected static function booted()
    {
        parent::booted();
        static::deleting(function ($file) {
            Storage::disk($file->disk)->delete($file->path);
        });

        static::updating(function ($file) {
            if ($file->isDirty('original_name')) {
                $newPath = dirname($file->path) . '/' . $file->original_name;
                Storage::disk($file->disk)->move($file->path, $newPath);
                $file->path = $newPath;
            }
        });
    }

    /**
     * Store a file and create a DB record.
     */
    public static function storeUploadedFile($uploadedFile, $disk = 'local', $userId = null)
    {
        $options = [];
        if ($disk === 's3') {
            $options['ContentDisposition'] = 'attachment; filename="' . $uploadedFile->getClientOriginalName() . '"';
        }

        $path = $uploadedFile->store('', [
            'disk' => $disk,
            'visibility' => 'public',
            ...$options,
        ]);

        return static::create([
            'original_name' => $uploadedFile->getClientOriginalName(),
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $uploadedFile->getClientMimeType(),
            'size' => $uploadedFile->getSize(),
            'user_id' => $userId,
        ]);
    }

    /**
     * Get the file URL (virtual/computed field).
     */
    public function getUrlAttribute()
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Determine if the file is an image (virtual/computed field).
     */
    public function getIsImageAttribute()
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
