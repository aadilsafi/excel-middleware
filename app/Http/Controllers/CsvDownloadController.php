<?php

namespace App\Http\Controllers;

use App\Models\ExcelProcessingJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CsvDownloadController extends Controller
{
    public function download(ExcelProcessingJob $job)
    {
        // Check if the CSV export exists
        if (!$job->csv_export_path || !Storage::exists($job->csv_export_path)) {
            abort(404, 'CSV file not found');
        }

        // Check if user has access to this job (optional security check)
        if (auth()->user()->id !== $job->user_id) {
            abort(403, 'Unauthorized access');
        }

        $filename = 'results_' . $job->filename . '_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return Storage::download($job->csv_export_path, $filename);
    }
}
