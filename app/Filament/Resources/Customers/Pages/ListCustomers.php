<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Pages\ImportCustomers;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import Bulk')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->url(ImportCustomers::getUrl()),
            CreateAction::make(),
        ];
    }
}
