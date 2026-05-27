<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class CustomerBulkSheetImport implements ToArray
{
    public function array(array $array): array
    {
        return $array;
    }
}
