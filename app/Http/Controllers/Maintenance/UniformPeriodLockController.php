<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\UniformPeriodLock;
use Exception;
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
            $query = UniformPeriodLock::query()->latest('id');

            if ($request->filled('lock_type')) {
                $this->authorizeLockType($request, $request->lock_type, 'view');
                $query->where('lock_type', $request->lock_type);
            } else {
                $query->whereIn('lock_type', $this->viewableLockTypes($request));
            }

            if ($request->has(['column_type', 'key_word'])) {
                $column = $request->column_type;
                if (in_array($column, ['period_name', 'lock_type', 'status', 'prepared_by', 'updated_by'], true)) {
                    $query->where($column, 'LIKE', '%' . $request->key_word . '%');
                }
            }

            return $this->sendResponse($query->paginate($perPage));
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function createUniformPeriodLock(Request $request)
    {
        try {
            $validated = $this->validated($request);
            $this->authorizeLockType($request, $validated['lock_type'], 'perform');

            $record = DB::transaction(function () use ($request, $validated) {
                $this->ensureNoOverlap($validated['lock_type'], $validated['date_from'], $validated['date_to']);

                if ($validated['status'] === 'active') {
                    UniformPeriodLock::where('lock_type', $validated['lock_type'])
                        ->where('status', 'active')
                        ->update([
                            'status' => 'inactive',
                            'updated_by' => optional($request->user())->proper_full_name,
                        ]);
                }

                return UniformPeriodLock::create([
                    ...$validated,
                    'prepared_by' => optional($request->user())->proper_full_name,
                ]);
            });

            return $this->sendResponse([
                'message' => 'Uniform period created successfully',
                'record' => $record,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function editUniformPeriodLock(Request $request, int $id)
    {
        try {
            $validated = $this->validated($request);
            $this->authorizeLockType($request, $validated['lock_type'], 'perform');

            $record = DB::transaction(function () use ($request, $validated, $id) {
                $record = UniformPeriodLock::lockForUpdate()->findOrFail($id);
                $this->ensureNoOverlap($validated['lock_type'], $validated['date_from'], $validated['date_to'], $id);

                if ($validated['status'] === 'active') {
                    UniformPeriodLock::where('id', '!=', $id)
                        ->where('lock_type', $validated['lock_type'])
                        ->where('status', 'active')
                        ->update([
                            'status' => 'inactive',
                            'updated_by' => optional($request->user())->proper_full_name,
                        ]);
                }

                $record->update([
                    ...$validated,
                    'updated_by' => optional($request->user())->proper_full_name,
                ]);

                return $record->fresh();
            });

            return $this->sendResponse([
                'message' => 'Uniform period updated successfully',
                'record' => $record,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'period_name' => ['required', 'string', 'max:100'],
            'lock_type' => ['required', Rule::in([
                UniformPeriodLock::TYPE_ISSUANCE,
                UniformPeriodLock::TYPE_RECEIVING,
                UniformPeriodLock::TYPE_ACKNOWLEDGEMENT,
            ])],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function ensureNoOverlap(string $lockType, string $dateFrom, string $dateTo, ?int $exceptId = null): void
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

    private function authorizeLockType(Request $request, string $lockType, string $action): void
    {
        $permission = $this->permissionFor($lockType, $action);

        if (!$permission || !$request->user()?->can($permission)) {
            throw ValidationException::withMessages([
                'permission' => "You are not allowed to {$action} {$lockType} period locks.",
            ]);
        }
    }

    private function viewableLockTypes(Request $request): array
    {
        return collect([UniformPeriodLock::TYPE_ISSUANCE, UniformPeriodLock::TYPE_RECEIVING])
            ->filter(fn (string $lockType) => $request->user()?->can($this->permissionFor($lockType, 'view')))
            ->values()
            ->all();
    }

    private function permissionFor(string $lockType, string $action): ?string
    {
        return match ($lockType) {
            UniformPeriodLock::TYPE_ISSUANCE => "{$action}_uniform_period_lock_issuance",
            UniformPeriodLock::TYPE_RECEIVING => "{$action}_uniform_period_lock_receiving",
            default => null,
        };
    }
}
