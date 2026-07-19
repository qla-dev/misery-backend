<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $names = config('game.synthetic_player_names');

        DB::table('games')
            ->where('is_synthetic', true)
            ->orderBy('id')
            ->chunkById(100, function ($games) use ($names): void {
                foreach ($games as $game) {
                    DB::table('games')->where('id', $game->id)->update([
                        'synthetic_host_name' => $names[array_rand($names)],
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Synthetic display names do not need to be restored.
    }
};
