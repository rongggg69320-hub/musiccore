<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('social_id');
            $table->string('facebook_id')->nullable()->unique()->after('google_id');
        });

        // Migrate existing social_id to the new columns
        DB::table('users')->where('social_provider', 'google')->update([
            'google_id' => DB::raw('social_id')
        ]);
        DB::table('users')->where('social_provider', 'facebook')->update([
            'facebook_id' => DB::raw('social_id')
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'facebook_id']);
        });
    }
};
