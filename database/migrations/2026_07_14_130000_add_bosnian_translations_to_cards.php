<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('title_bs')->nullable()->after('title');
            $table->text('subtitle_bs')->nullable()->after('subtitle');
        });
    }

    public function down(): void
    {
        Schema::table('cards', fn (Blueprint $table) => $table->dropColumn(['title_bs', 'subtitle_bs']));
    }
};
