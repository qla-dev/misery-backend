<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('cards')
            ->select(['id', 'subtitle'])
            ->whereNotNull('subtitle')
            ->orderBy('id')
            ->chunkById(100, function ($cards): void {
                foreach ($cards as $card) {
                    $subtitle = trim((string) $card->subtitle);
                    $normalized = preg_replace('/\.+$/u', '', $subtitle) ?? $subtitle;

                    if ($normalized !== $card->subtitle) {
                        DB::table('cards')->where('id', $card->id)->update(['subtitle' => $normalized]);
                    }
                }
            });

        DB::table('cards')
            ->where('title', 'Fire Sprinklers Destroy an Art Exhibition')
            ->update([
                'title_bs' => 'Protupožarne prskalice su uništile izložbu umjetnina',
                'subtitle_bs' => 'Lažna dojava poplavila je svaki eksponat nekoliko minuta prije otvaranja.',
            ]);

        DB::table('cards')
            ->where('title', 'An Avalanche Blocks the Only Road')
            ->update(['title_bs' => 'Lavina je blokirala jedinu cestu']);

        DB::table('cards')
            ->where('title', 'Your Dog Destroys the Wedding Cake')
            ->update(['title_bs' => 'Vaš pas je uništio svadbenu tortu']);
    }

    public function down(): void
    {
        // Content normalization is intentionally not reversible.
    }
};
