<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stacks', function (Blueprint $table) {
            $table->string('color', 7)->default('#facc15')->after('slug');
            $table->string('icon_key', 40)->default('sparkles')->after('color');
            $table->string('description')->nullable()->after('icon_key');
            $table->string('description_bs')->nullable()->after('description');
        });

        DB::table('stacks')->where('slug', 'normal')->update([
            'color' => '#facc15',
            'icon_key' => 'sparkles',
            'description' => 'Funny and awkward situations',
            'description_bs' => 'Smiješne i čudne situacije',
        ]);
        DB::table('stacks')->where('slug', 'spicy')->update([
            'color' => '#fb7185',
            'icon_key' => 'flame',
            'description' => 'Friendly, absurd and wildly unfortunate',
            'description_bs' => 'Prijateljski, apsurdno i divlje',
        ]);
        DB::table('stacks')->where('slug', '18-plus')->update([
            'color' => '#ef4444',
            'icon_key' => 'shield-alert',
            'description' => 'Explicit sexual situations for adults only',
            'description_bs' => 'Eksplicitne seksualne situacije samo za odrasle',
        ]);
    }

    public function down(): void
    {
        Schema::table('stacks', function (Blueprint $table) {
            $table->dropColumn(['color', 'icon_key', 'description', 'description_bs']);
        });
    }
};
