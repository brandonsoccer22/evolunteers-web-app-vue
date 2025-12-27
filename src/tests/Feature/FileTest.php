<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_uploaded_file_creates_record_and_file(): void
    {
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
    }

    public function test_file_delete_removes_file_from_disk(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('uploads/sample.jpg', 'content');
        $file = File::factory()->create([
            'path' => 'uploads/sample.jpg',
            'disk' => 'local',
        ]);

        $file->delete();

        Storage::disk('local')->assertMissing('uploads/sample.jpg');
    }

    public function test_file_rename_moves_file_on_disk(): void
    {
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
    }
}
