<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('moves', function(Blueprint $table){ $table->id(); $table->foreignId('game_id')->constrained()->cascadeOnDelete(); $table->foreignId('player_id')->constrained('users')->cascadeOnDelete(); $table->foreignId('card_id')->nullable()->constrained()->nullOnDelete(); $table->boolean('correct'); $table->timestamps(); }); } public function down(): void { Schema::dropIfExists('moves'); } };
