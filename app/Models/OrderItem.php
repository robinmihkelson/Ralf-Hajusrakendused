<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_description',
        'product_image_url',
        'unit_amount_cents',
        'quantity',
        'line_total_cents',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
