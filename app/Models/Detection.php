<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Detection extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'image_path', 'detected_at', 'status', 'description', 'location'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function detectionResults()
    {
        return $this->hasMany(DetectionResult::class);
    }

    public function recommendations()
    {
        return $this->hasMany(Recommendation::class);
    }
}
