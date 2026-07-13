<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_orders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 40);
            $table->text('address');
            $table->unsignedTinyInteger('quantity');
            $table->decimal('unit_price', 8, 2);
            $table->decimal('total', 8, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->string('language', 5)->default('bs');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_orders');
    }
};
