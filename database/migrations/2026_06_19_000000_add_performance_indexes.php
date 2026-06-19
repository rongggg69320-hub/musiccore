<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->index('status');
            $table->index('genre_id');
            $table->index('album_id');
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->foreignId('genre_id')->nullable()->constrained()->nullOnDelete();
            $table->index('status');
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('google_id');
            $table->index('facebook_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['genre_id']);
            $table->dropIndex(['album_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['google_id']);
            $table->dropIndex(['facebook_id']);
            $table->dropIndex(['created_at']);
        });
    }
};
