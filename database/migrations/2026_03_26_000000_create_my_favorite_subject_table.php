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
        Schema::create('my_favorite_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('image');
            $table->text('description');
            $table->string('brand', 120);
            $table->unsignedSmallInteger('production_year');
            $table->unsignedSmallInteger('horsepower');
            $table->timestamps();

            $table->index(['brand', 'production_year']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('my_favorite_subject');
    }
};
