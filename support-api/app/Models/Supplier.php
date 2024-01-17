<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model {
    use HasFactory;
    protected $fillable = ['name', 'description', 'logo_url'];

    public function brands() {
        return $this->hasMany(Brand::class);
    }
}
