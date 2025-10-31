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
        Schema::create('data_columns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('data_source_id')->nullable();
            $table->foreign('data_source_id')->references('id')->on('data_sources')->cascadeOnDelete();
            $table->string('name', 512);
            $table->string('type', 512);
            $table->timestamps(6);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_columns');
    }
};
