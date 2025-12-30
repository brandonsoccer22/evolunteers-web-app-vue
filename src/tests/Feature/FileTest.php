<?php

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('store uploaded file creates record and file', function (): void {
    Storage::fake('local');

    $user = User::factory()->create();
    $upload = UploadedFile::fake()->image('avatar.jpg');

    $file = File::storeUploadedFile($upload, 'local', $user->id);

    $this->assertDatabaseHas('files', [
        'id' => $file->id,
        'original_name' => 'avatar.jpg',
        'disk' => 'local',
        'user_id' => $user->id,
    ]);
    Storage::disk('local')->assertExists($file->path);
});

test('file delete removes file from disk', function (): void {
    Storage::fake('local');

    Storage::disk('local')->put('uploads/sample.jpg', 'content');
    $file = File::factory()->create([
        'path' => 'uploads/sample.jpg',
        'disk' => 'local',
    ]);

    $file->delete();

    Storage::disk('local')->assertMissing('uploads/sample.jpg');
});

test('file rename moves file on disk', function (): void {
    Storage::fake('local');

    Storage::disk('local')->put('uploads/original.jpg', 'content');
    $file = File::factory()->create([
        'original_name' => 'original.jpg',
        'path' => 'uploads/original.jpg',
        'disk' => 'local',
    ]);

    $file->update(['original_name' => 'renamed.jpg']);

    $file->refresh();
    $this->assertSame('uploads/renamed.jpg', $file->path);
    Storage::disk('local')->assertExists('uploads/renamed.jpg');
    Storage::disk('local')->assertMissing('uploads/original.jpg');
});
