<?php

namespace App\Filament\Pages;

use App\DataTransferObjects\CustomerBulkImportResult;
use App\Exports\CustomerImportTemplateExport;
use App\Services\CustomerBulkImportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @property-read Schema $form
 */
class ImportCustomers extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Import Customers';

    protected static ?string $title = 'Import Bulk Customer & Group';

    protected static ?int $navigationSort = 5;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?CustomerBulkImportResult $lastImportResult = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Upload File Excel')
                    ->description('Gunakan template .xlsx / .xls. Kolom: Nama Customer, No Telp, Group.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('File Excel')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->required()
                            ->maxSize(5120)
                            ->disk('local')
                            ->directory('imports')
                            ->visibility('private'),
                    ]),
                Section::make('Hasil Import Terakhir')
                    ->schema([
                        Placeholder::make('import_summary')
                            ->label('')
                            ->content(fn (): HtmlString => new HtmlString($this->formatImportSummary()))
                            ->visible(fn (): bool => $this->lastImportResult !== null),
                    ])
                    ->visible(fn (): bool => $this->lastImportResult !== null)
                    ->collapsed(false),
            ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new CustomerImportTemplateExport,
            'template-import-customer-group.xlsx',
        );
    }

    public function import(): void
    {
        $data = $this->form->getState();
        $filePath = $data['file'] ?? null;

        if (is_array($filePath)) {
            $filePath = $filePath[0] ?? null;
        }

        if (! $filePath) {
            Notification::make()
                ->title('File belum dipilih')
                ->danger()
                ->send();

            return;
        }

        $absolutePath = Storage::disk('local')->path($filePath);

        try {
            $this->lastImportResult = app(CustomerBulkImportService::class)
                ->importFromFile($absolutePath);

            Storage::disk('local')->delete($filePath);

            $this->form->fill(['file' => null]);

            Notification::make()
                ->title('Import selesai')
                ->body($this->formatImportNotificationBody())
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Import gagal')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Download Template')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action('downloadTemplate'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('import-customers-form')
                    ->livewireSubmitHandler('import')
                    ->footer([
                        Actions::make([
                            Action::make('import')
                                ->label('Proses Import')
                                ->icon(Heroicon::OutlinedArrowUpTray)
                                ->submit('import'),
                        ])
                            ->alignment(Alignment::Start),
                    ]),
            ]);
    }

    protected function formatImportSummary(): string
    {
        $result = $this->lastImportResult;

        if (! $result) {
            return '';
        }

        $lines = [
            "<strong>Total baris:</strong> {$result->totalRows}",
            "<strong>Berhasil diproses:</strong> {$result->processedRows}",
            "<strong>Customer baru:</strong> {$result->customersCreated}",
            "<strong>Customer diperbarui:</strong> {$result->customersUpdated}",
            "<strong>Group baru:</strong> {$result->groupsCreated}",
            "<strong>Group dipakai ulang:</strong> {$result->groupsReused}",
            "<strong>Assign ke group baru:</strong> {$result->assignmentsCreated}",
            "<strong>Assign sudah ada:</strong> {$result->assignmentsExisting}",
            "<strong>Baris gagal:</strong> {$result->skippedRows}",
        ];

        if ($result->errors !== []) {
            $lines[] = '<br><strong>Detail error:</strong><ul style="margin-top:0.5rem">';
            foreach (array_slice($result->errors, 0, 20) as $error) {
                $lines[] = "<li>Baris {$error['row']}: {$error['message']}</li>";
            }
            if (count($result->errors) > 20) {
                $lines[] = '<li>... dan lainnya</li>';
            }
            $lines[] = '</ul>';
        }

        return implode('<br>', $lines);
    }

    protected function formatImportNotificationBody(): string
    {
        $result = $this->lastImportResult;

        if (! $result) {
            return '';
        }

        return "Diproses: {$result->processedRows} baris. Customer baru: {$result->customersCreated}, diperbarui: {$result->customersUpdated}. Group baru: {$result->groupsCreated}. Gagal: {$result->skippedRows}.";
    }
}
