<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('opportunity_organization', function (Blueprint $table) {
        $table->sequence()->primary();
        $table->boolean('is_opportunity_owner')->default(true);
        $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
        $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
        $table->timestampAudits();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunity_organization');
    }
};
