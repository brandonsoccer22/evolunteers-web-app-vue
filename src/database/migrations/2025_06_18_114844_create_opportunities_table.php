<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->sequence()->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('profile_image_id')->nullable()->constrained('files')->nullOnDelete();
            $table->timestampAudits();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
