<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExcelProcessingJobResource\Pages;
use App\Filament\Resources\ExcelProcessingJobResource\RelationManagers;
use App\Models\ExcelProcessingJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;

class ExcelProcessingJobResource extends Resource
{
    protected static ?string $model = ExcelProcessingJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';
    
    protected static ?string $navigationLabel = 'Excel Processing';
    
    protected static ?string $modelLabel = 'Excel Upload';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('file_path')
                    ->label('Upload Excel File')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                    ->disk('public')
                    ->directory('excel-uploads')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('filename')
                    ->label('File Name')
                    ->searchable()
                    ->sortable(),
                    
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ]),
                    
                TextColumn::make('total_rows')
                    ->label('Total Rows')
                    ->sortable(),
                    
                TextColumn::make('processed_rows')
                    ->label('Processed')
                    ->sortable(),
                    
                TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->formatStateUsing(fn ($state) => $state . '%'),
                    
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable(),
                    
                TextColumn::make('processing_completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download_csv')
                    ->label('Download CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ExcelProcessingJob $record) => $record->csv_export_path ? route('download.csv', $record->id) : null)
                    ->visible(fn (ExcelProcessingJob $record) => $record->status === 'completed' && $record->csv_export_path),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExcelProcessingJobs::route('/'),
            'create' => Pages\CreateExcelProcessingJob::route('/create'),
            'edit' => Pages\EditExcelProcessingJob::route('/{record}/edit'),
        ];
    }
}
