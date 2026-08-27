<?php

namespace App\Http\Controllers\Uniform;

use App\Exports\Maintenance\UniformItemExport;
use App\Http\Controllers\Controller;
use App\Models\Sizes;
use App\Models\Supplier;
use App\Models\UniformGender;
use App\Models\UniformItem;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ItemMasterlistController extends Controller
{
    public function getAllItems(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;
            $items = UniformItem::with(['size', 'gender', 'suppliers']);

            if ($request->has(['column_type', 'key_word'])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (in_array($column, ['item_no', 'description', 'status'])) {
                    $items->where($column, 'LIKE', '%' . $keyword . '%');
                }
            }

            if ($request->has('status') && $request->status !== 'all') {
                $items->where('status', $request->status);
            }

            if ($request->filled('uniform_size_id')) {
                $items->where('uniform_size_id', $request->uniform_size_id);
            }

            if ($request->has('export') && $request->export) {
                return Excel::download(
                    new UniformItemExport($items->orderBy('item_no')->get()),
                    'uniform-item-masterlist.xls'
                );
            }

            return $this->sendResponse($items->orderBy('item_no')->paginate($perPage));
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function exportAllItems(Request $request)
    {
        $request['export'] = true;
        return $this->getAllItems($request);
    }

    public function getOptions()
    {
        try {
            return $this->sendResponse([
                'sizes' => Sizes::where('status', 'active')->orderBy('description')->get(),
                'genders' => UniformGender::where('status', 'active')->orderBy('description')->get(),
                'suppliers' => Supplier::orderBy('vendor_name')->get(),
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function createItem(Request $request)
    {
        try {
            $data = $this->validatedData($request);

            DB::transaction(function () use ($request, $data) {
                $item = UniformItem::create([
                    ...$data,
                    'created_by' => optional($request->user())->proper_full_name,
                ]);

                $this->syncSuppliers($item, $request->suppliers ?? []);
            });

            return $this->sendResponse(['message' => 'Uniform item created successfully']);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function editItem(Request $request, $id)
    {
        try {
            $item = UniformItem::findOrFail($id);
            $data = $this->validatedData($request);

            DB::transaction(function () use ($request, $item, $data) {
                $item->update([
                    ...$data,
                    'updated_by' => optional($request->user())->proper_full_name,
                ]);

                $this->syncSuppliers($item, $request->suppliers ?? []);
            });

            return $this->sendResponse(['message' => 'Uniform item updated successfully']);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255', 'unique:uniform_items,description'],
            'uniform_size_id' => ['required', 'exists:sizes,id'],
            'uniform_gender_id' => ['required', 'exists:uniform_genders,id'],
            'standard_unit_price' => ['required', 'numeric', 'min:0'],
            'stock_on_hand' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'standard_qty' => ['required', 'numeric', 'min:0'],
            'standard_unit' => ['nullable', 'string', 'max:50'],
            'min_order' => ['required', 'numeric', 'min:0'],
            'order_limit' => ['required', 'numeric', 'gte:min_order'],
            'crit_level' => ['required', 'numeric', 'min:0'],
            'minimum_rpl' => ['required', 'numeric', 'min:0'],
            'free_uniform' => ['required', 'boolean'],
            'made_to_order' => ['required', 'boolean'],
            'suppliers' => ['array', 'max:3'],
            'suppliers.*.vendor_no' => ['required_with:suppliers', 'string', 'exists:suppliers,vendor_number'],
            'suppliers.*.unit_price' => ['required_with:suppliers', 'numeric', 'min:0.01'],
            'suppliers.*.packaging_unit' => ['nullable', 'string', 'max:50'],
            'suppliers.*.remarks' => ['nullable', 'string', 'max:500'],
        ],
        [
            'description.unique' => "This description already exist.Please enter unique description"
        ]);

        unset($data['suppliers']);

        return $data;
    }
    private function syncSuppliers(UniformItem $item, array $suppliers): void
    {
        $item->suppliers()->delete();

        foreach ($suppliers as $supplier) {
            $uniformSupplier = Supplier::where('vendor_number', $supplier['vendor_no'])->first();

            $item->suppliers()->create([
                'vendor_no' => $supplier['vendor_no'],
                'vendor_name' => $uniformSupplier?->vendor_name ?? $supplier['vendor_no'],
                'unit_price' => $supplier['unit_price'],
                'packaging_unit' => $supplier['packaging_unit'] ?? null,
                'remarks' => $supplier['remarks'] ?? null,
            ]);
        }
    }
}
