<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExcelProcessingJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'error_message',
        'csv_export_path',
        'user_id',
        'processing_started_at',
        'processing_completed_at',
    ];

    protected $casts = [
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExcelProcessingResult::class);
    }
    
    public function getProgressPercentageAttribute(): float
    {
        if (!$this->total_rows || $this->total_rows === 0) {
            return 0;
        }
        
        return round(($this->processed_rows / $this->total_rows) * 100, 2);
    }
}
