<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\UniformFreeUniformLimit;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UniformFreeUniformLimitController extends Controller
{
    public function getUniformFreeUniformLimits(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $limits = UniformFreeUniformLimit::query()->latest('id');

            if ($request->has(['column_type', 'key_word'])) {
                $limits->where($request->column_type, 'LIKE', '%' . $request->key_word . '%');
            }

            return $this->sendResponse($limits->paginate($perPage));
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function createUniformFreeUniformLimit(Request $request)
    {
        try {
            $validated = $request->validate([
                'description' => ['required', 'string', 'max:255', 'unique:uniform_free_uniform_limits,description'],
                'allowed_count' => ['required', 'integer', 'min:1', 'max:100'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
                'date_from' => ['required','date'],
                'date_to' => ['required','date','after_or_equal:date_from'],
            ]);

            $limit = DB::transaction(function () use ($request, $validated) {
                if ($validated['status'] === 'active') {
                    UniformFreeUniformLimit::query()->update(['status' => 'inactive']);
                }

                return UniformFreeUniformLimit::create([
                    ...$validated,
                    'created_by' => optional($request->user())->proper_full_name,
                ]);
            });

            return $this->sendResponse([
                'message' => 'Free uniform limit created successfully.',
                'record' => $limit,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function editUniformFreeUniformLimit(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'description' => ['required', 'string', 'max:255', Rule::unique('uniform_free_uniform_limits', 'description')->ignore($id)],
                'allowed_count' => ['required', 'integer', 'min:1', 'max:100'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
                'date_from' => ['required', 'date'],
                'date_to' => ['required', 'date', 'after_or_equal:date_from']
            ]);

            $limit = DB::transaction(function () use ($request, $id, $validated) {
                $limit = UniformFreeUniformLimit::lockForUpdate()->findOrFail($id);

                if ($validated['status'] === 'active') {
                    UniformFreeUniformLimit::query()
                        ->where('id', '<>', $id)
                        ->update(['status' => 'inactive']);
                }

                $limit->update([
                    ...$validated,
                    'updated_by' => optional($request->user())->proper_full_name,
                ]);

                return $limit->fresh();
            });

            return $this->sendResponse([
                'message' => 'Free uniform limit updated successfully.',
                'record' => $limit,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }
}
