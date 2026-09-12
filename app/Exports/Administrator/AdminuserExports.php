<?php

namespace App\Exports\Administrator;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class AdminuserExports implements FromCollection, WithHeadings
{
    protected Collection $users;

    public function __construct(Collection $users)
    {
        $this->users = $users;
    }
    public function collection()
    {
        return $this->users->map(function ($user) {
            return [
                $user->employee_number,
                $user->first_name,
                $user->middle_name,
                $user->last_name,
                $user->email,
                $user->roles->pluck('name')->implode(', '),
                $user->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee Number',
            'First Name',
            'Middle Name',
            'Last Name',
            'Email',
            'Role',
            'Status'
        ];
    }
}
