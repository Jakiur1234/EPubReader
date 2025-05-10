<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Magazine extends Model
{
    protected $fillable = ['title', 'description', 'cover_image'];

    public function variations()
    {
        return $this->hasMany(Variations::class);
    }
}
