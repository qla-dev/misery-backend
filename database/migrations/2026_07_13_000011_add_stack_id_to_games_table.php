<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('stack_id')->nullable()->after('current_card_id')->constrained('stacks')->nullOnDelete();
        });
        $normalId = DB::table('stacks')->where('slug', 'normal')->value('id');
        DB::table('games')->update(['stack_id' => $normalId]);
    }
    public function down(): void { Schema::table('games', fn (Blueprint $table) => $table->dropConstrainedForeignId('stack_id')); }
};
