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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('role_id', 36);
            $table->foreign('role_id')->references('id')->on('roles');
            $table->string('name', 128);
            $table->string('email', 64)->index();
            $table->string('password', 64);
            $table->text('profile_pic')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps(6);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
