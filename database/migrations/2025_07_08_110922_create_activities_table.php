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
        Schema::create('activity_log_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('activity_type');
            $table->string('name');
            $table->text('message')->nullable();
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('activityable');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_log_activities');
    }
};