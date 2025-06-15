<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExcelProcessingResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'excel_processing_job_id',
        'row_number',
        'manufacturer',
        'manufacturer_part_number',
        'openai_response',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'openai_response' => 'array',
        'processed_at' => 'datetime',
    ];

    public function excelProcessingJob(): BelongsTo
    {
        return $this->belongsTo(ExcelProcessingJob::class);
    }
}
