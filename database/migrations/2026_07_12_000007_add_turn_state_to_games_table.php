<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('current_player_id')->nullable()->after('current_card_id')->constrained('users')->nullOnDelete();
            $table->foreignId('turn_owner_id')->nullable()->after('current_player_id')->constrained('users')->nullOnDelete();
            $table->boolean('awaiting_finish')->default(false)->after('current_player_id');
            $table->boolean('is_steal_turn')->default(false)->after('awaiting_finish');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_player_id');
            $table->dropConstrainedForeignId('turn_owner_id');
            $table->dropColumn('awaiting_finish');
            $table->dropColumn('is_steal_turn');
        });
    }
};
