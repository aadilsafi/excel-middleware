<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('excel_processing_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excel_processing_job_id')->constrained()->onDelete('cascade');
            $table->integer('row_number');
            $table->string('manufacturer');
            $table->string('manufacturer_part_number');
            $table->json('openai_response')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excel_processing_results');
    }
};
