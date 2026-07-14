<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('cards', fn (Blueprint $table) => $table->boolean('status')->default(true)->index()->after('score')); }
    public function down(): void { Schema::table('cards', fn (Blueprint $table) => $table->dropColumn('status')); }
};
