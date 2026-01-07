<?php

namespace App\Exports\Maintenance;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Spatie\Permission\Models\Role;

class ATDAccessTypeExport implements FromCollection, WithHeadings
{
    protected Collection $records;

    public function __construct(Collection $records)
    {
        $this->records = $records;
    }

    public function collection()
    {
        $roleIds = $this->records
            ->pluck('roles')
            ->flatten()
            ->filter()
            ->unique();

        $roleMap = $roleIds->isNotEmpty()
            ? Role::whereIn('id', $roleIds)->pluck('name', 'id')
            : collect();

        return $this->records->map(function ($record) use ($roleMap) {
            $roles = collect((array) ($record->roles_display ?? $record->roles))
                ->map(fn($id) => $roleMap[$id] ?? $id)
                ->join(', ');
            return [
                $record->deduction?->description ?? "",
                $roles,
                $record->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            "Deduction",
            "Role",
            "Status",
        ];
    }
}
