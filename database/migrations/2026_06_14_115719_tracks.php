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
       Schema::create('tracks', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('artist_name');

            $table->string('album')->nullable();

            $table->foreignId('genre_id')
                ->nullable()
                ->constrained('genres')
                ->nullOnDelete();

            $table->string('audio_file');
            $table->string('cover_image')->nullable();

            $table->enum('status', [
                'processing',
                'published',
                'rejected'
            ])->default('processing');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('genres');
    }
};
