<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('games', function(Blueprint $table){ $table->id(); $table->string('code',8)->unique(); $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete(); $table->boolean('started')->default(false); $table->foreignId('current_card_id')->nullable()->constrained('cards')->nullOnDelete(); $table->timestamps(); }); } public function down(): void { Schema::dropIfExists('games'); } };
