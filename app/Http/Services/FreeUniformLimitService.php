<?php
namespace App\Http\Services;

use App\Models\RequestAndIssuance;
use App\Models\UniformFreeUniformLimit;
use App\Models\Employee;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class FreeUniformLimitService
{
    public function check(
        array $validated,
        ?int $excludeId = null
    ): ?RequestAndIssuance {

        if (($validated['issuance_type'] ?? null) !== 'Free Uniform') {
            return null;
        }
        $limit = UniformFreeUniformLimit::query()
            ->where('status', 'active')
            ->whereDate('date_from', '<=', today())
            ->orderByDesc('date_from')
            ->orderByDesc('id')
            ->first();
        if (!$limit) {
            throw ValidationException::withMessages([
                'issuance_type' => 'There is currently no active Free Uniform limit configured.',
            ]);
        }

        $allowedCount = (int) $limit->allowed_count;
        $employee = Employee::query()
            ->where('employee_number', $validated['employee_id'])
            ->first();

        if (!$employee || !$employee->date_hired) {
            throw ValidationException::withMessages([
                'employee_id' => 'Employee was not found or has no hiring date.',
            ]);
        }
        $hireDate = Carbon::parse($employee->date_hired)->startOfDay();
        $today = today();

        $periodStart = $hireDate->copy();

        while ($periodStart->copy()->addYear()->lte($today)) {
            $periodStart->addYear();
        }

        $periodEnd = $periodStart->copy()
            ->addYear()
            ->subDay();
        $count = RequestAndIssuance::query()
            ->where('request_and_issuances.employee_id', $validated['employee_id'])
            ->where('request_and_issuances.status', '<>', 'Cancelled')
            ->where('request_and_issuances.issuance_type', 'Free Uniform')
            ->where('request_and_issuances.request_number', 'LIKE', 'FR-URN%')
            ->when(
                $excludeId,
                fn ($query) => $query->where(
                    'request_and_issuances.id',
                    '<>',
                    $excludeId
                )
            )
            ->whereBetween(
                'request_and_issuances.date_requested',
                [
                    $periodStart->copy()->startOfDay(),
                    $periodEnd->copy()->endOfDay(),
                ]
            )
            ->join(
                'request_and_issuance_items',
                'request_and_issuances.request_number',
                '=',
                'request_and_issuance_items.requested_number_id'
            )
            ->sum('request_and_issuance_items.requested_quantity');
        $currentRequestQty = collect($validated['items'] ?? [])
            ->sum(function ($item) {
                return (int) ($item['requested_quantity'] ?? 0);
            });
        $totalQty = (int) $count + (int) $currentRequestQty;
        if ($totalQty > $allowedCount) {
            return RequestAndIssuance::query()
                ->where('employee_id', $validated['employee_id'])
                ->where('status', '<>', 'Cancelled')
                ->where('issuance_type', 'Free Uniform')
                ->where('request_number', 'LIKE', 'FR-URN%')
                ->when(
                    $excludeId,
                    fn ($query) => $query->where('id', '<>', $excludeId)
                )
                ->latest('id')
                ->first();
        }
        return null;
    }
    
}
