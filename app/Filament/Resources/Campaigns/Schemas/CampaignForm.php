<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use App\Enums\ButtonType;
use App\Enums\DelayType;
use App\Enums\MediaType;
use App\Enums\MessageType;
use App\Enums\SenderMode;
use App\Models\Sender;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Campaign')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Campaign')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('customer_group_id')
                            ->label('Group Customer')
                            ->relationship('customerGroup', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Pengirim')
                    ->schema([
                        Select::make('sender_mode')
                            ->label('Mode Sender')
                            ->options(collect(SenderMode::cases())->mapWithKeys(
                                fn (SenderMode $mode) => [$mode->value => $mode->label()]
                            ))
                            ->default(SenderMode::Fixed->value)
                            ->live()
                            ->required(),
                        Select::make('sender_id')
                            ->label('Nomor Sender')
                            ->options(fn () => Sender::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('sender_mode') === SenderMode::Fixed->value)
                            ->required(fn (Get $get): bool => $get('sender_mode') === SenderMode::Fixed->value),
                    ])
                    ->columns(2),

                Section::make('Pesan')
                    ->schema([
                        Select::make('message_type')
                            ->label('Tipe Pesan')
                            ->options(collect(MessageType::cases())->mapWithKeys(
                                fn (MessageType $type) => [$type->value => $type->label()]
                            ))
                            ->default(MessageType::Text->value)
                            ->live()
                            ->required(),
                        Textarea::make('message')
                            ->label('Isi Pesan')
                            ->rows(4)
                            ->visible(fn (Get $get): bool => in_array($get('message_type'), [
                                MessageType::Text->value,
                                MessageType::Button->value,
                            ], true))
                            ->required(fn (Get $get): bool => in_array($get('message_type'), [
                                MessageType::Text->value,
                                MessageType::Button->value,
                            ], true))
                            ->columnSpanFull(),
                        TextInput::make('footer')
                            ->label('Footer (opsional)')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('message_type') === MessageType::Button->value),
                        TextInput::make('button_image_url')
                            ->label('URL Gambar (opsional)')
                            ->url()
                            ->visible(fn (Get $get): bool => $get('message_type') === MessageType::Button->value),
                        Repeater::make('buttons')
                            ->label('Tombol (maks. 5)')
                            ->schema([
                                Select::make('type')
                                    ->label('Tipe')
                                    ->options(collect(ButtonType::cases())->mapWithKeys(
                                        fn (ButtonType $type) => [$type->value => $type->label()]
                                    ))
                                    ->default(ButtonType::Reply->value)
                                    ->live()
                                    ->required(),
                                TextInput::make('displayText')
                                    ->label('Display Text')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('phoneNumber')
                                    ->label('No. Telepon')
                                    ->visible(fn (Get $get): bool => $get('type') === ButtonType::Call->value)
                                    ->required(fn (Get $get): bool => $get('type') === ButtonType::Call->value),
                                TextInput::make('url')
                                    ->label('URL')
                                    ->url()
                                    ->visible(fn (Get $get): bool => $get('type') === ButtonType::Url->value)
                                    ->required(fn (Get $get): bool => $get('type') === ButtonType::Url->value),
                                TextInput::make('copyCode')
                                    ->label('Copy Code')
                                    ->visible(fn (Get $get): bool => $get('type') === ButtonType::Copy->value)
                                    ->required(fn (Get $get): bool => $get('type') === ButtonType::Copy->value),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->maxItems(5)
                            ->visible(fn (Get $get): bool => $get('message_type') === MessageType::Button->value)
                            ->columnSpanFull(),
                        Select::make('media_type')
                            ->label('Tipe Media')
                            ->options(collect(MediaType::cases())->mapWithKeys(
                                fn (MediaType $type) => [$type->value => $type->label()]
                            ))
                            ->default(MediaType::Image->value)
                            ->visible(fn (Get $get): bool => $get('message_type') === MessageType::Media->value)
                            ->required(fn (Get $get): bool => $get('message_type') === MessageType::Media->value),
                        FileUpload::make('media_path')
                            ->label('Upload File')
                            ->disk('public')
                            ->directory('campaign-media')
                            ->acceptedFileTypes([
                                'image/*',
                                'video/*',
                                'audio/*',
                                'application/pdf',
                            ])
                            ->maxSize(10240)
                            ->visible(fn (Get $get): bool => $get('message_type') === MessageType::Media->value)
                            ->required(fn (Get $get): bool => $get('message_type') === MessageType::Media->value)
                            ->columnSpanFull(),
                        Textarea::make('caption')
                            ->label('Caption')
                            ->rows(3)
                            ->visible(fn (Get $get): bool => $get('message_type') === MessageType::Media->value)
                            ->columnSpanFull(),
                    ]),

                Section::make('Delay / Timer')
                    ->schema([
                        Select::make('delay_type')
                            ->label('Tipe Delay')
                            ->options(collect(DelayType::cases())->mapWithKeys(
                                fn (DelayType $type) => [$type->value => $type->label()]
                            ))
                            ->default(DelayType::PerMessage->value)
                            ->live()
                            ->required(),
                        TextInput::make('delay_seconds')
                            ->label('Detik')
                            ->numeric()
                            ->default(10)
                            ->minValue(1)
                            ->required()
                            ->helperText(fn (Get $get): string => $get('delay_type') === DelayType::PerBatch->value
                                ? 'Interval antar batch (mis: 30 detik tiap batch)'
                                : 'Jeda antar pesan (mis: 10 detik per pesan)'),
                        TextInput::make('batch_size')
                            ->label('Jumlah Pesan per Batch')
                            ->numeric()
                            ->default(10)
                            ->minValue(1)
                            ->visible(fn (Get $get): bool => $get('delay_type') === DelayType::PerBatch->value)
                            ->required(fn (Get $get): bool => $get('delay_type') === DelayType::PerBatch->value),
                    ])
                    ->columns(3),

                Section::make('Penjadwalan')
                    ->schema([
                        Toggle::make('is_scheduled')
                            ->label('Jadwalkan Campaign')
                            ->live()
                            ->default(false),
                        DateTimePicker::make('scheduled_at')
                            ->label('Waktu Mulai')
                            ->seconds(false)
                            ->minDate(now())
                            ->visible(fn (Get $get): bool => (bool) $get('is_scheduled'))
                            ->required(fn (Get $get): bool => (bool) $get('is_scheduled')),
                    ])
                    ->columns(2),
            ]);
    }
}
