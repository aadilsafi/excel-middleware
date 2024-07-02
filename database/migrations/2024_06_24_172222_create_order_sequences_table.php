<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('order_sequences', function (Blueprint $table) {
            $table->id();
            $table->integer('current_sequence')->default(0);
            $table->timestamps();
        });

        // Insert initial sequence record
        DB::table('order_sequences')->insert(['current_sequence' => 0]);
    }

    public function down()
    {
        Schema::dropIfExists('order_sequences');
    }
};
