<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stacks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::table('cards', function (Blueprint $table) {
            $table->foreignId('stack_id')->nullable()->after('deck')->constrained('stacks')->nullOnDelete();
        });
        $now = now();
        DB::table('stacks')->insert([
            ['name' => 'Normal', 'slug' => 'normal', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Spicy', 'slug' => 'spicy', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $normalId = DB::table('stacks')->where('slug', 'normal')->value('id');
        DB::table('cards')->update(['deck' => 'normal', 'stack_id' => $normalId]);
    }

    public function down(): void
    {
        Schema::table('cards', fn (Blueprint $table) => $table->dropConstrainedForeignId('stack_id'));
        Schema::dropIfExists('stacks');
    }
};
