<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->string('tokenable_type', 191)->change();
            });

            if (! Schema::hasIndex('personal_access_tokens', ['tokenable_type', 'tokenable_id'])) {
                Schema::table('personal_access_tokens', function (Blueprint $table) {
                    $table->index(['tokenable_type', 'tokenable_id']);
                });
            }

            if (! Schema::hasIndex('personal_access_tokens', ['token'])) {
                Schema::table('personal_access_tokens', function (Blueprint $table) {
                    $table->unique('token');
                });
            }

            return;
        }

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type', 191);
            $table->unsignedBigInteger('tokenable_id');
            $table->index(['tokenable_type', 'tokenable_id']);
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
