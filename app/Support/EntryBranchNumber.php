<?php

namespace App\Support;

class EntryBranchNumber
{
    public static function normalizeForCurrentCompany(?string $branchNumber): ?string
    {
        if (env('COMPANY_NAME') === 'TTFC' && $branchNumber === 'SFI00010') {
            return 'TTFC00035';
        }

        return $branchNumber;
    }

    public static function normalizeEntriesForCurrentCompany(array $entries): array
    {
        return array_map(function ($entry) {
            if (isset($entry['branch_number'])) {
                $entry['branch_number'] = self::normalizeForCurrentCompany($entry['branch_number']);
            }

            return $entry;
        }, $entries);
    }
}
