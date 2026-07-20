<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Services\Bir1601CPdfService;
use App\Http\Services\Bir1601CService;
use App\Models\Bir1601C;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Bir1601CController extends Controller
{
    public function __construct(
        private Bir1601CService $birService,
        private Bir1601CPdfService $birPdfService
    ) {
    }

    public function index(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $bir = Bir1601C::query()->orderBy('year', 'DESC')->orderBy('month', 'DESC');

            if ($request->has('year')) {
                $bir = $bir->where('year', $request->year);
            }

            if ($request->has('month')) {
                $bir = $bir->where('month', $request->month);
            }

            $bir = $bir->paginate($perPage);

            return $this->sendResponse($bir);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        try {
            $bir = Bir1601C::findOrFail($id);

            return $this->sendResponse($bir);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function generate(Request $request)
    {
        try {
            $validated = $request->validate([
                'year' => ['required', 'integer', 'min:2000'],
                'month' => ['required', 'integer', 'min:1', 'max:12'],
            ]);

            $existing = Bir1601C::where('year', $validated['year'])->where('month', $validated['month'])->first();

            if ($existing && $existing->status === Bir1601C::STATUS_FINAL) {
                return $this->sendError(['message' => 'This period has already been finalized and cannot be regenerated.']);
            }

            $sectionA = $this->birService->computeSectionA((int) $validated['year'], (int) $validated['month']);

            $bir = Bir1601C::firstOrNew(['year' => $validated['year'], 'month' => $validated['month']]);
            $bir->disableLogging();

            $bir->fill(array_merge($sectionA, [
                'generated_by' => Auth::id(),
                'generated_at' => now(),
            ]))->save();

            $taxable = $this->birService->computeTaxableCompensation($bir->toArray());
            $bir->update($taxable);

            $remittance = $this->birService->computeDerivedRemittance($bir->toArray());
            $bir->update($remittance);

            $bir->enableLogging();

            activity()
                ->performedOn($bir)
                ->causedBy(Auth::user())
                ->useLog('BIR 1601-C')
                ->event('generated')
                ->log('generated');

            return $this->sendResponse($bir->fresh());
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $bir = Bir1601C::findOrFail($id);

            if ($bir->status === Bir1601C::STATUS_FINAL) {
                return $this->sendError(['message' => 'This period has already been finalized and can no longer be edited.']);
            }

            $validated = $request->validate([
                'de_minimis_benefits' => ['nullable', 'numeric'],
                'other_non_taxable_compensation' => ['nullable', 'numeric'],
                'add_less_adjustment' => ['nullable', 'numeric'],
                'previous_month_1_period' => ['nullable', 'string'],
                'previous_month_1_date_paid' => ['nullable', 'date'],
                'previous_month_1_bank' => ['nullable', 'string'],
                'previous_month_1_ror_number' => ['nullable', 'string'],
                'previous_month_1_tax_paid' => ['nullable', 'numeric'],
                'previous_month_1_tax_due' => ['nullable', 'numeric'],
                'previous_month_2_period' => ['nullable', 'string'],
                'previous_month_2_date_paid' => ['nullable', 'date'],
                'previous_month_2_bank' => ['nullable', 'string'],
                'previous_month_2_ror_number' => ['nullable', 'string'],
                'previous_month_2_tax_paid' => ['nullable', 'numeric'],
                'previous_month_2_tax_due' => ['nullable', 'numeric'],
                'previous_month_3_period' => ['nullable', 'string'],
                'previous_month_3_date_paid' => ['nullable', 'date'],
                'previous_month_3_bank' => ['nullable', 'string'],
                'previous_month_3_ror_number' => ['nullable', 'string'],
                'previous_month_3_tax_paid' => ['nullable', 'numeric'],
                'previous_month_3_tax_due' => ['nullable', 'numeric'],
                'remarks' => ['nullable', 'string'],
            ]);

            $original = $bir->only(array_keys($validated));

            $bir->disableLogging();

            $bir->update($validated);

            $taxable = $this->birService->computeTaxableCompensation($bir->toArray());
            $bir->update($taxable);

            $remittance = $this->birService->computeDerivedRemittance($bir->toArray());
            $bir->update($remittance);

            $bir->enableLogging();

            $updated = $bir->fresh()->only(array_keys($validated));
            $changed = array_keys(array_diff_assoc(
                array_map(fn ($value) => (string) $value, $updated),
                array_map(fn ($value) => (string) $value, $original)
            ));

            if (!empty($changed)) {
                activity()
                    ->performedOn($bir)
                    ->causedBy(Auth::user())
                    ->useLog('BIR 1601-C')
                    ->event('updated')
                    ->withProperties([
                        'old' => array_intersect_key($original, array_flip($changed)),
                        'attributes' => array_intersect_key($updated, array_flip($changed)),
                    ])
                    ->log('edited');
            }

            return $this->sendResponse($bir->fresh());
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function finalize($id)
    {
        try {
            $bir = Bir1601C::findOrFail($id);

            if ($bir->status === Bir1601C::STATUS_FINAL) {
                return $this->sendError(['message' => 'This period has already been finalized.']);
            }

            $remittance = $this->birService->computeDerivedRemittance($bir->toArray());

            $bir->update(array_merge($remittance, [
                'status' => Bir1601C::STATUS_FINAL,
                'finalized_by' => Auth::id(),
                'finalized_at' => now(),
            ]));

            return $this->sendResponse($bir->fresh());
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function export($id)
    {
        try {
            $bir = Bir1601C::findOrFail($id);

            $pdfContent = $this->birPdfService->generate($bir);

            $filename = 'BIR-1601C-' . $bir->year . '-' . str_pad((string) $bir->month, 2, '0', STR_PAD_LEFT) . '.pdf';

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }
}
