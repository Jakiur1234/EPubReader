<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItems extends Model
{
    protected $fillable = ['order_id', 'variation_id', 'quantity', 'price', 'total'];

    public function order()
    {
        return $this->belongsTo(Orders::class);
    }

    public function variation()
    {
        return $this->belongsTo(Variations::class);
    }
}
