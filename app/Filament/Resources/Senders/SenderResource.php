<?php

namespace App\Filament\Resources\Senders;

use App\Filament\Resources\Senders\Pages\CreateSender;
use App\Filament\Resources\Senders\Pages\EditSender;
use App\Filament\Resources\Senders\Pages\ListSenders;
use App\Filament\Resources\Senders\Schemas\SenderForm;
use App\Filament\Resources\Senders\Tables\SendersTable;
use App\Models\Sender;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SenderResource extends Resource
{
    protected static ?string $model = Sender::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = 'Sender';

    protected static ?string $modelLabel = 'Sender';

    protected static ?string $pluralModelLabel = 'Senders';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SenderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SendersTable::configure($table);
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
            'index' => ListSenders::route('/'),
            'create' => CreateSender::route('/create'),
            'edit' => EditSender::route('/{record}/edit'),
        ];
    }
}
