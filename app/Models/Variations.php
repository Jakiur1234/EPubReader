<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Variations extends Model
{
    protected $fillable = ['magazine_id', 'title', 'issue_date', 'price', 'epub_file'];

    public function magazine()
    {
        return $this->belongsTo(Magazine::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItems::class);
    }
}
