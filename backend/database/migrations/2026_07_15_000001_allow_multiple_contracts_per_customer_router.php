<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('router_customer')) {
            return;
        }

        Schema::table('router_customer', function (Blueprint $table) {
            $table->dropUnique(['router_id', 'customer_id']);
            $table->unique(['router_id', 'contract_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('router_customer')) {
            return;
        }

        Schema::table('router_customer', function (Blueprint $table) {
            $table->dropUnique(['router_id', 'contract_id']);
            $table->unique(['router_id', 'customer_id']);
        });
    }
};
