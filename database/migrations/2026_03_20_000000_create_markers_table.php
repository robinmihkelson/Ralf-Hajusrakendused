<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('latitude', 10, 6);
            $table->decimal('longitude', 10, 6);
            $table->text('description')->nullable();
            $table->timestamp('added')->useCurrent();
            $table->timestamp('edited')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markers');
    }
};
