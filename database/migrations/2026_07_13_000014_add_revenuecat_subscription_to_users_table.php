<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pro_status')->default('inactive')->after('color');
            $table->timestamp('pro_started_at')->nullable()->after('pro_status');
            $table->timestamp('pro_ends_at')->nullable()->after('pro_started_at');
            $table->string('revenuecat_product_id')->nullable()->after('pro_ends_at');
            $table->string('revenuecat_entitlement_id')->nullable()->after('revenuecat_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'pro_status',
                'pro_started_at',
                'pro_ends_at',
                'revenuecat_product_id',
                'revenuecat_entitlement_id',
            ]);
        });
    }
};
