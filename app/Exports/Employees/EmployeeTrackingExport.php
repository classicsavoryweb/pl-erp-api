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

    protected function isOicAssignment($employee): bool
    {
        if (($employee->oic_status ?? null) === 'OIC') {
            return true;
        }

        $latestPosition = $employee->latest_position ?? null;
        if ($latestPosition && ($latestPosition->oic_assignment ?? null) === 'Yes') {
            return true;
        }

        return false;
    }

    protected function appendOicToPosition($employee, $column, $value)
    {
        if ($column !== 'current_position.description' || !$value) {
            return $value;
        }

        if ($this->isOicAssignment($employee) && !str_ends_with($value, ' (OIC)')) {
            return $value . ' (OIC)';
        }

        return $value;
    }

    public function collection()
    {
        return $this->employees->map(function ($employee) {
            $row = [];

            foreach ($this->columns as $column) {
                $value = $this->getNestedProperty($employee, $column);
            if (in_array($column, ['date_hired', 'regularization_date', 'separation_date']) && $value) {
                $value = \Carbon\Carbon::parse($value)->format('m/d/Y');
            }
                $value = $this->appendOicToPosition($employee, $column, $value);
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
