<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedSmallInteger('target_score')->default(7)->after('stack_id');
            $table->foreignId('winner_id')->nullable()->after('turn_owner_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('winner_id');
            $table->dropColumn('target_score');
        });
    }
};
