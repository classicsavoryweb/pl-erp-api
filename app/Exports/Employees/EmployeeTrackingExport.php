<?php

namespace App\Exports\Employees;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeTrackingExport implements FromCollection, WithHeadings
{
    protected Collection $employees;
    protected $headers;
    protected $columns;

    public function __construct(Collection $employees, $headers, $columns)
    {
        $this->employees = $employees;
        $this->headers = $headers;
        $this->columns = $columns;
    }

    protected function getNestedProperty($object, $path)
    {
        $keys = explode('.', $path);

        foreach ($keys as $key) {
            if (is_array($object)) {
                $object = $object[$key] ?? '';
            } elseif (is_object($object)) {
                $object = $object->$key ?? '';
            } else {
                return '';
            }
        }

        return $object;
    }

    public function collection()
    {
        return $this->employees->map(function ($employee) {
            $row = [];
            // return [
            //     $employee->employee_number,
            //     $employee->employee_number_2,
            //     $employee->last_name,
            //     $employee->middle_name,
            //     $employee->first_name,
            //     $employee->email,
            //     $employee->current_position->description ?? '',
            //     $employee->current_branch->name ?? '',
            //     $employee->date_hired,
            //     $employee->employee_status->description ?? "",
            //     $employee->status,
            // ];

            foreach ($this->columns as $column) {
                $value = $this->getNestedProperty($employee, $column);
                $row[] = $value;
            }

            return $row;
        });
    }

    public function headings(): array
    {
        return $this->headers;
    }
}
