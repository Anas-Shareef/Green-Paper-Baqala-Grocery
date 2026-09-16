<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomerImportService
{
    /**
     * Import customers from a CSV file.
     *
     * @param string $filePath Absolute path to CSV file
     * @param string $duplicateStrategy 'skip' | 'update' | 'merge'
     * @return array Summary report
     */
    public function importCsv(string $filePath, string $duplicateStrategy = 'skip'): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \Exception("CSV file does not exist or is not readable.");
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Failed to open CSV file.");
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new \Exception("CSV file is empty or invalid.");
        }

        // Normalize headers
        $normalizedHeader = array_map(function ($h) {
            return strtolower(trim(str_replace([' ', '_', '-'], '', $h)));
        }, $header);

        $nameIndex = array_search('name', $normalizedHeader);
        $phoneIndex = array_search('phone', $normalizedHeader);
        if ($phoneIndex === false) {
            $phoneIndex = array_search('mobile', $normalizedHeader);
        }
        $villaIndex = array_search('villanumber', $normalizedHeader);
        if ($villaIndex === false) {
            $villaIndex = array_search('villa', $normalizedHeader);
        }
        $zoneIndex = array_search('zone', $normalizedHeader);

        if ($nameIndex === false || $phoneIndex === false) {
            fclose($handle);
            throw new \Exception("CSV file must contain at least 'Name' and 'Phone' columns.");
        }

        $totalRows = 0;
        $importedCount = 0;
        $duplicateCount = 0;
        $invalidCount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $totalRows++;

                $name = trim($row[$nameIndex] ?? '');
                $rawPhone = trim($row[$phoneIndex] ?? '');
                $phone = preg_replace('/[^0-9]/', '', $rawPhone);
                $villa = ($villaIndex !== false) ? trim($row[$villaIndex] ?? '') : null;
                $zone = ($zoneIndex !== false) ? trim($row[$zoneIndex] ?? '') : null;

                // Validate phone number
                if (strlen($phone) < 7 || strlen($phone) > 15) {
                    $invalidCount++;
                    $errors[] = "Row #{$totalRows}: Invalid phone number '{$rawPhone}'";
                    continue;
                }

                if (empty($name)) {
                    $invalidCount++;
                    $errors[] = "Row #{$totalRows}: Customer name is required.";
                    continue;
                }

                $existingCustomer = Customer::where('phone', $phone)->first();

                if ($existingCustomer) {
                    $duplicateCount++;

                    if ($duplicateStrategy === 'skip') {
                        continue;
                    } elseif ($duplicateStrategy === 'update' || $duplicateStrategy === 'merge') {
                        $existingCustomer->update([
                            'name' => $name ?: $existingCustomer->name,
                            'villa_number' => $villa ?: $existingCustomer->villa_number,
                            'zone' => $zone ?: $existingCustomer->zone,
                            'whatsapp_number' => $phone,
                        ]);
                        $importedCount++;
                    }
                } else {
                    Customer::create([
                        'name' => $name,
                        'phone' => $phone,
                        'whatsapp_number' => $phone,
                        'villa_number' => $villa,
                        'zone' => $zone,
                        'status' => 'active',
                    ]);
                    $importedCount++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            throw $e;
        }

        fclose($handle);

        return [
            'total_rows' => $totalRows,
            'imported' => $importedCount,
            'duplicates' => $duplicateCount,
            'invalid' => $invalidCount,
            'errors' => array_slice($errors, 0, 10), // return top 10 errors
        ];
    }
}
