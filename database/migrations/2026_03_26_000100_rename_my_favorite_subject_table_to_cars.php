<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('my_favorite_subject') && ! Schema::hasTable('cars')) {
            Schema::rename('my_favorite_subject', 'cars');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cars') && ! Schema::hasTable('my_favorite_subject')) {
            Schema::rename('cars', 'my_favorite_subject');
        }
    }
};
