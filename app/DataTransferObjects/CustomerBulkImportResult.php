<?php

namespace App\DataTransferObjects;

class CustomerBulkImportResult
{
    public int $totalRows = 0;

    public int $processedRows = 0;

    public int $customersCreated = 0;

    public int $customersUpdated = 0;

    public int $groupsCreated = 0;

    public int $groupsReused = 0;

    public int $assignmentsCreated = 0;

    public int $assignmentsExisting = 0;

    public int $skippedRows = 0;

    /** @var array<int, array{row: int, message: string}> */
    public array $errors = [];

    public function addError(int $row, string $message): void
    {
        $this->errors[] = ['row' => $row, 'message' => $message];
        $this->skippedRows++;
    }
}
