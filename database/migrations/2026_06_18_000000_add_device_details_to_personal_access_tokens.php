<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('device_name')->nullable()->after('abilities');
            $table->string('platform')->nullable()->after('device_name');
            $table->string('platform_version')->nullable()->after('platform');
            $table->string('ip_address', 45)->nullable()->after('platform_version');
            $table->text('user_agent')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn([
                'device_name',
                'platform',
                'platform_version',
                'ip_address',
                'user_agent',
            ]);
        });
    }
};
