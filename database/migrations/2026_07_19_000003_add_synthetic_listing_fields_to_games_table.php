<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('is_synthetic')->default(false)->index()->after('is_private');
            $table->string('synthetic_host_name')->nullable()->after('is_synthetic');
            $table->unsignedTinyInteger('synthetic_player_count')->nullable()->after('synthetic_host_name');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['is_synthetic', 'synthetic_host_name', 'synthetic_player_count']);
        });
    }
};
