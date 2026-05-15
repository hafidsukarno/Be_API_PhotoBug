<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Village extends Model
{
    use HasFactory;

    protected $fillable = ['village_name', 'district', 'penyuluh_id'];

    public function users()
    {
        return $this->hasMany(User::class); // Petani yang terdaftar di desa ini
    }

    public function penyuluh()
    {
        return $this->belongsTo(User::class, 'penyuluh_id'); // Penyuluh yang membawahi desa ini
    }
}
