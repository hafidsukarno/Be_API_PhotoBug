<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetectionResult extends Model
{
    use HasFactory;

    protected $fillable = ['detection_id', 'pest_name', 'confidence'];
    protected $table = 'detection_results';

    public function detection()
    {
        return $this->belongsTo(Detection::class);
    }
}
