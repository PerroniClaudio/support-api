<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model {
    use HasFactory;

    protected $fillable = ['name', 'description', 'logo_url', 'supplier_id'];

    public function supplier() {
        return $this->belongsTo(Supplier::class);
    }
}
