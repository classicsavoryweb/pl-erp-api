<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\UniformPeriodLock;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UniformPeriodLockController extends Controller
{
    public function getUniformPeriodLocks(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;
            $query = UniformPeriodLock::query();

            if ($request->filled('lock_type')) {
                $query->where('lock_type', $request->lock_type);
            }

            if ($request->has(['column_type', 'key_word'])) {
                $column = $request->column_type;
                if (in_array($column, ['period_name', 'lock_type', 'date_from', 'date_to', 'status', 'prepared_by', 'updated_by'], true)) {
                    $query->where($column, 'LIKE', '%' . $request->key_word . '%');
                }
            }

            if ($request->boolean('combined')) {
                return $this->sendResponse($this->periodList($query, $perPage, $request));
            }

            return $this->sendResponse($query->latest('id')->paginate($perPage));
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function saveUniformPeriodLockPeriod(Request $request)
    {
        try {
            $validated = $this->validatedPeriod($request);

            $records = DB::transaction(function () use ($request, $validated) {
                $periodName = $this->periodName($validated['date_from'], $validated['date_to']);

                $issuance = $this->savePeriodLockType(
                    $request,
                    UniformPeriodLock::TYPE_ISSUANCE,
                    $validated['issuance_status'],
                    $validated['date_from'],
                    $validated['date_to'],
                    $periodName,
                    $validated['issuance_id'] ?? null,
                );

                $receiving = $this->savePeriodLockType(
                    $request,
                    UniformPeriodLock::TYPE_RECEIVING,
                    $validated['receiving_status'],
                    $validated['date_from'],
                    $validated['date_to'],
                    $periodName,
                    $validated['receiving_id'] ?? null,
                );

                return compact('issuance', 'receiving');
            });

            return $this->sendResponse([
                'message' => 'Uniform period lock saved successfully',
                'record' => $records,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    private function validatedPeriod(Request $request): array
    {
        return $request->validate([
            'issuance_id' => ['nullable', 'integer', 'exists:uniform_period_locks,id'],
            'receiving_id' => ['nullable', 'integer', 'exists:uniform_period_locks,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'issuance_status' => ['required', Rule::in(['active', 'inactive'])],
            'receiving_status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function savePeriodLockType(
        Request $request,
        string $lockType,
        string $status,
        string $dateFrom,
        string $dateTo,
        string $periodName,
        ?int $id = null,
    ): UniformPeriodLock {
        $this->checkOverlap($lockType, $dateFrom, $dateTo, $id);

        if ($status === 'active') {
            UniformPeriodLock::query()
                ->when($id, fn ($query) => $query->where('id', '!=', $id))
                ->where('lock_type', $lockType)
                ->where('status', 'active');
             
        }

        $values = [
            'period_name' => $periodName,
            'lock_type' => $lockType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => $status,
        ];

        if ($id) {
            $record = UniformPeriodLock::lockForUpdate()->findOrFail($id);
            $record->update([
                ...$values,
                'updated_by' => optional($request->user())->proper_full_name,
            ]);

            return $record->fresh();
        }

        return UniformPeriodLock::create([
            ...$values,
            'prepared_by' => optional($request->user())->proper_full_name,
        ]);
    }

    private function periodName(string $dateFrom, string $dateTo): string
    {
        return date('M d, Y', strtotime($dateFrom)) . ' - ' . date('M d, Y', strtotime($dateTo));
    }

    private function periodList($query, int $perPage, Request $request): LengthAwarePaginator
    {
        $periods = $query
            ->orderBy('date_from', 'desc')
            ->orderBy('date_to', 'desc')
            ->orderBy('lock_type')
            ->get()
            ->groupBy(fn (UniformPeriodLock $record) => $record->date_from->format('Y-m-d') . '|' . $record->date_to->format('Y-m-d'))
            ->map(function ($records, string $key) {
                [$dateFrom, $dateTo] = explode('|', $key);

                return [
                    'id' => $key,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'issuance' => $records->firstWhere('lock_type', UniformPeriodLock::TYPE_ISSUANCE),
                    'receiving' => $records->firstWhere('lock_type', UniformPeriodLock::TYPE_RECEIVING),
                ];
            })
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $periods->forPage($page, $perPage)->values(),
            $periods->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function checkOverlap(string $lockType, string $dateFrom, string $dateTo, ?int $exceptId = null): void
    {
        $hasOverlap = UniformPeriodLock::query()
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->where('lock_type', $lockType)
            ->whereDate('date_from', '<=', $dateTo)
            ->whereDate('date_to', '>=', $dateFrom)
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'date_from' => "Uniform {$lockType} period overlaps with an existing period.",
            ]);
        }
    }

}
