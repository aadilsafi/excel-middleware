<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEshipFilesJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessEshipCommand extends Command
{
    protected $signature = 'app:process-eship {ftpName=rsr}';
    protected $description = 'Process ESHIP files from FTP';

    public function handle(): void
    {
        try {
            $ftp_name = $this->argument('ftpName') ?? 'rsr';
            Log::info("Processing FTP ESHIP files for orders from {$ftp_name}");

            $files = Storage::disk($ftp_name)->files('eo/outgoing');
            $eshipFiles = [];

            foreach ($files as $file) {
                if (stripos($file, 'ESHIP') !== false) {
                    $eshipFiles[] = [
                        'path' => $file,
                        'ftp' => $ftp_name
                    ];
                }
            }

            if (empty($eshipFiles)) {
                $this->info('No ESHIP files found.');
                return;
            }

            foreach ($eshipFiles as $file) {
                ProcessEshipFilesJob::dispatch($file['path'], $file['ftp']);
                $this->info("Queued file for processing: {$file['path']} from {$file['ftp']}");
            }

            $this->info('All ESHIP files have been queued for processing.');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $this->error('An error occurred while queueing ESHIP files.');
        }
    }
}
