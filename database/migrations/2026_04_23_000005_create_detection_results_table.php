<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detection_id')->constrained('detections')->cascadeOnDelete();
            $table->string('pest_name');
            $table->decimal('confidence', 5, 2); // e.g., 95.50
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detection_results');
    }
};
