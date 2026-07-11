<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('cards', function(Blueprint $table){ $table->id(); $table->string('title'); $table->decimal('score',5,1); $table->string('image')->default('0'); $table->string('deck')->default('normal'); $table->timestamps(); }); } public function down(): void { Schema::dropIfExists('cards'); } };
