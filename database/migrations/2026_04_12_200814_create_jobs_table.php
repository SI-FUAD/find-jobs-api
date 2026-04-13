<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id', 20)->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('title');
            $table->enum('level', ['Fresher', 'Entry Level', 'Mid Level', 'Senior']);
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('vacancy')->default(1);
            $table->string('experience')->nullable();
            $table->string('salary')->default('Negotiable');
            $table->date('date_posted');
            $table->date('deadline');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
