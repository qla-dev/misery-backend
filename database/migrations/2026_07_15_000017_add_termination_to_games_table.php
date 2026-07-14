<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('terminated_at')->nullable()->after('host_in_lobby');
            $table->string('termination_reason', 40)->nullable()->after('terminated_at');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['terminated_at', 'termination_reason']);
        });
    }
};
