<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->sequence()->primary();
            $table->string('name')->unique();
            $table->timestampAudits();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
