<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Services\AppSettings;
use App\Services\WhatsAppGatewayService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
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
use Filament\Support\Exceptions\Halt;
use Throwable;

/**
 * @property-read Schema $form
 */
class WhatsAppSettings extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'WhatsApp Gateway Settings';

    protected static ?int $navigationSort = 99;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'whatsapp_api_url' => AppSetting::get(AppSettings::WHATSAPP_API_URL, 'https://wa.forfunforlife.com'),
            'whatsapp_api_key' => AppSetting::get(AppSettings::WHATSAPP_API_KEY),
        ]);
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
                Section::make('WhatsApp Gateway API')
                    ->description('Konfigurasi URL dan API Key untuk WhatsApp gateway.')
                    ->schema([
                        TextInput::make('whatsapp_api_url')
                            ->label('API Base URL')
                            ->required()
                            ->url()
                            ->placeholder('https://wa.forfunforlife.com')
                            ->helperText('Contoh endpoint: /send-message, /send-media, /send-button'),
                        TextInput::make('whatsapp_api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->required()
                            ->maxLength(255),
                    ]),
            ]);
    }

    public function testConnection(WhatsAppGatewayService $gateway): void
    {
        $result = $gateway->testConnection();

        if ($result['success']) {
            Notification::make()
                ->title('Koneksi gateway OK')
                ->body($result['message'])
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Koneksi gateway gagal')
            ->body($result['message'])
            ->danger()
            ->duration(15000)
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Test Koneksi Gateway')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action('testConnection'),
        ];
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();

            $data = $this->form->getState();

            AppSetting::set(AppSettings::WHATSAPP_API_URL, rtrim($data['whatsapp_api_url'], '/'));
            AppSetting::set(AppSettings::WHATSAPP_API_KEY, $data['whatsapp_api_key']);

            $this->commitDatabaseTransaction();

            Notification::make()
                ->title('Settings berhasil disimpan')
                ->success()
                ->send();
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction()
                ? $this->rollBackDatabaseTransaction()
                : $this->commitDatabaseTransaction();
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('whatsapp-settings-form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Simpan Settings')
                                ->submit('save'),
                        ])
                            ->alignment(Alignment::Start),
                    ]),
            ]);
    }
}
