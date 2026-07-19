<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('games')
            ->where('is_synthetic', true)
            ->orderBy('id')
            ->chunkById(100, function ($games): void {
                foreach ($games as $game) {
                    DB::table('games')->where('id', $game->id)->update([
                        'synthetic_player_count' => random_int(0, 8),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Synthetic display counts do not need to be restored.
    }
};
