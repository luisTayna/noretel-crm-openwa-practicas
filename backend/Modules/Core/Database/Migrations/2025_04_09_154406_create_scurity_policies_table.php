<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('security_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('policy_type');
            $table->json('configuration');
            $table->boolean('active')->default(true);
            $table->timestamp('update_date');
            $table->string('version');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('scurity_policies');
    }
};
