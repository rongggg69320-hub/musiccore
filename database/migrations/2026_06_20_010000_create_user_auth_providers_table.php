<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_auth_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('firebase_uid');
            $table->string('email')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->unique(['provider', 'firebase_uid']);
            $table->index('firebase_uid');
            $table->index('email');
        });

        DB::table('users')
            ->whereNotNull('firebase_uid')
            ->orderBy('id')
            ->select(['id', 'email', 'firebase_uid'])
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    DB::table('user_auth_providers')->updateOrInsert(
                        ['provider' => 'email', 'firebase_uid' => $user->firebase_uid],
                        [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'last_used_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });

        DB::table('users')
            ->whereNotNull('google_id')
            ->orderBy('id')
            ->select(['id', 'email', 'google_id'])
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    DB::table('user_auth_providers')->updateOrInsert(
                        ['provider' => 'google', 'firebase_uid' => $user->google_id],
                        [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'last_used_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });

        DB::table('users')
            ->whereNotNull('facebook_id')
            ->orderBy('id')
            ->select(['id', 'email', 'facebook_id'])
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    DB::table('user_auth_providers')->updateOrInsert(
                        ['provider' => 'facebook', 'firebase_uid' => $user->facebook_id],
                        [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'last_used_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_auth_providers');
    }
};
