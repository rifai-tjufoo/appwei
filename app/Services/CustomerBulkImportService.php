<?php

namespace App\Services;

use App\DataTransferObjects\CustomerBulkImportResult;
use App\Imports\CustomerBulkSheetImport;
use App\Models\Customer;
use App\Models\CustomerGroup;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class CustomerBulkImportService
{
    /**
     * @return array<int, array<int, mixed>>
     */
    public function readRows(string $filePath): array
    {
        $sheets = Excel::toArray(new CustomerBulkSheetImport, $filePath);

        return $sheets[0] ?? [];
    }

    public function importFromFile(string $filePath): CustomerBulkImportResult
    {
        $rows = $this->readRows($filePath);
        $result = new CustomerBulkImportResult;

        if ($rows === []) {
            return $result;
        }

        $startIndex = $this->isHeaderRow($rows[0]) ? 1 : 0;

        DB::transaction(function () use ($rows, $startIndex, $result): void {
            foreach ($rows as $index => $row) {
                if ($index < $startIndex) {
                    continue;
                }

                $excelRowNumber = $index + 1;
                $result->totalRows++;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                try {
                    $this->importRow($row, $result);
                    $result->processedRows++;
                } catch (Throwable $exception) {
                    $result->addError($excelRowNumber, $exception->getMessage());
                }
            }
        });

        return $result;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    protected function importRow(array $row, CustomerBulkImportResult $result): void
    {
        $name = trim((string) ($row[0] ?? ''));
        $phone = $this->normalizePhone((string) ($row[1] ?? ''));
        $groupName = trim((string) ($row[2] ?? ''));

        if ($name === '' || $phone === '' || $groupName === '') {
            throw new \InvalidArgumentException('Nama Customer, No Telp, dan Group wajib diisi.');
        }

        if (strlen($phone) < 8) {
            throw new \InvalidArgumentException('No Telp tidak valid.');
        }

        $group = CustomerGroup::query()->firstOrCreate(
            ['name' => $groupName],
            ['description' => null],
        );

        if ($group->wasRecentlyCreated) {
            $result->groupsCreated++;
        } else {
            $result->groupsReused++;
        }

        $customer = Customer::query()->where('phone', $phone)->first();
        $customerWasCreated = false;
        $customerWasUpdated = false;

        if ($customer) {
            if ($customer->name !== $name) {
                $customer->update(['name' => $name]);
                $customerWasUpdated = true;
            }
        } else {
            $customer = Customer::query()->create([
                'name' => $name,
                'phone' => $phone,
            ]);
            $customerWasCreated = true;
        }

        if ($customerWasCreated) {
            $result->customersCreated++;
        } elseif ($customerWasUpdated) {
            $result->customersUpdated++;
        }

        $alreadyInGroup = $group->customers()
            ->where('customers.id', $customer->id)
            ->exists();

        if ($alreadyInGroup) {
            $result->assignmentsExisting++;
        } else {
            $group->customers()->attach($customer->id);
            $result->assignmentsCreated++;
        }
    }

    /**
     * @param  array<int, mixed>  $row
     */
    protected function isHeaderRow(array $row): bool
    {
        $first = strtolower(trim((string) ($row[0] ?? '')));

        return in_array($first, [
            'nama customer',
            'nama',
            'name',
            'customer name',
        ], true);
    }

    /**
     * @param  array<int, mixed>  $row
     */
    protected function isEmptyRow(array $row): bool
    {
        return trim((string) ($row[0] ?? '')) === ''
            && trim((string) ($row[1] ?? '')) === ''
            && trim((string) ($row[2] ?? '')) === '';
    }

    protected function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        return $normalized;
    }
}
