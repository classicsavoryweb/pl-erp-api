<?php

namespace App\Exports\Employees;

use Carbon\Carbon;
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

        $dateHiredIndex = array_search('date_hired', $this->columns, true);
        if ($dateHiredIndex !== false) {
            array_splice($this->columns, $dateHiredIndex + 1, 0, '__tenure');
            array_splice($this->headers, $dateHiredIndex + 1, 0, 'Tenure');
        }
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

    protected function getTenure($employee): string
    {
        if (!$employee->date_hired) {
            return '';
        }

        $dateHired = Carbon::parse($employee->date_hired)->startOfDay();
        $endDate = !empty($employee->separation_date) && $employee->separation_date !== '1900-01-01'
            ? Carbon::parse($employee->separation_date)->startOfDay()
            : now()->startOfDay();

        if ($endDate->lt($dateHired)) {
            return '';
        }

        $years = $dateHired->diffInYears($endDate);
        $cursor = $dateHired->copy()->addYears($years);
        $months = $cursor->diffInMonths($endDate);
        $cursor->addMonths($months);
        $days = $cursor->diffInDays($endDate);

        return sprintf(
            '%d %s, %d %s, %d %s',
            $years,
            $years === 1 ? 'year' : 'years',
            $months,
            $months === 1 ? 'month' : 'months',
            $days,
            $days === 1 ? 'day' : 'days'
        );
    }

    public function collection()
    {
        return $this->employees->map(function ($employee) {
            $row = [];

            foreach ($this->columns as $column) {
                if ($column === '__tenure') {
                    $row[] = $this->getTenure($employee);
                    continue;
                }

                $value = $this->getNestedProperty($employee, $column);
                if ($column === 'separation_reason') {
                    $value = $employee->separation?->description ?? $value;
                }
                if (in_array($column, ['date_hired', 'regularization_date', 'separation_date', 'birthday']) && $value) {
                    $value = Carbon::parse($value)->format('m/d/Y');
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
