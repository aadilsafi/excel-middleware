<?php

namespace App\Filament\Resources\ExcelProcessingJobResource\Pages;

use App\Filament\Resources\ExcelProcessingJobResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExcelProcessingJob extends EditRecord
{
    protected static string $resource = ExcelProcessingJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
