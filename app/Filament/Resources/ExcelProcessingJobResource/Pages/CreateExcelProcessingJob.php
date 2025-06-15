<?php

namespace App\Filament\Resources\ExcelProcessingJobResource\Pages;

use App\Filament\Resources\ExcelProcessingJobResource;
use App\Services\ExcelProcessingService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateExcelProcessingJob extends CreateRecord
{
    protected static string $resource = ExcelProcessingJobResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['status'] = 'pending';
        
        // Get the actual filename from the uploaded file
        if (isset($data['file_path'])) {
            $uploadedFile = $data['file_path'];
            if (is_string($uploadedFile)) {
                // The file_path is already the stored path
                $data['filename'] = basename($uploadedFile);
            }
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            $excelProcessingService = app(ExcelProcessingService::class);
            $excelProcessingService->processUploadedFile($this->record);

            Notification::make()
                ->title('Excel file uploaded successfully')
                ->body('Processing has started. You will see progress updates in the table.')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('Processing failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
