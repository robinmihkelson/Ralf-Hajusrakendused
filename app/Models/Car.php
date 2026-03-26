<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Car extends Model
{
    use HasFactory;

    protected $table = 'cars';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'image',
        'description',
        'brand',
        'production_year',
        'horsepower',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'production_year' => 'integer',
            'horsepower' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
