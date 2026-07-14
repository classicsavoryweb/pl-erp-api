<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\Bir2316Export;
use App\Http\Controllers\Controller;
use App\Http\Services\Bir2316PdfService;
use App\Imports\Bir2316Import;
use App\Models\Bir2316;
use App\Models\BirSetting;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class Bir2316Controller extends Controller
{
    public function __construct(
        private Bir2316PdfService $birPdfService
    ) {
    }

    public function index(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $bir = Bir2316::query()->with('employee:id,employee_number,first_name,middle_name,last_name,home_branch_id')
                ->orderBy('year', 'DESC')
                ->orderBy('id', 'DESC');

            if ($request->has('year')) {
                $bir = $bir->where('year', $request->year);
            }

            if ($request->has('schedule_type')) {
                $bir = $bir->where('schedule_type', $request->schedule_type);
            }

            if ($request->has('employee_id')) {
                $bir = $bir->where('employee_id', $request->employee_id);
            }

            if ($request->boolean('export')) {
                $filename = 'BIR-2316-' . ($request->year ?? 'all') . '.xlsx';

                return Excel::download(
                    new Bir2316Export($bir->get()),
                    $filename,
                    \Maatwebsite\Excel\Excel::XLSX
                );
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
            $bir = Bir2316::with('employee')->findOrFail($id);

            return $this->sendResponse($bir);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function import(Request $request)
    {
        try {
            $validated = $request->validate([
                'bir_2316_file' => ['required', 'file'],
                'year' => ['required', 'integer', 'min:2000'],
                'schedule_type' => ['required', 'string', 'in:' . Bir2316::SCHEDULE_1 . ',' . Bir2316::SCHEDULE_2],
            ]);

            $storage = Storage::disk('hr_system_folder');
            $directory = '/imports';

            if (!$storage->exists($directory)) {
                $storage->makeDirectory($directory);
            }

            $file = $request->file('bir_2316_file');
            $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
            $filePath = $directory . '/' . $fileName;
            $storage->put($filePath, file_get_contents($file));

            $import = new Bir2316Import((int) $validated['year'], $validated['schedule_type']);
            Excel::import($import, $storage->path($filePath));

            $scheduleLabel = $validated['schedule_type'] === Bir2316::SCHEDULE_1
                ? 'Schedule 1 (Taxable)'
                : 'Schedule 2 (Non-Taxable)';

            activity()
                ->causedBy(Auth::user())
                ->useLog('BIR 2316')
                ->event('imported')
                ->withProperties([
                    'year' => $validated['year'],
                    'schedule_type' => $scheduleLabel,
                    'file_name' => $file->getClientOriginalName(),
                    'imported' => $import->imported,
                ])
                ->log("imported {$import->imported} certificate(s) for {$scheduleLabel} {$validated['year']}");

            return $this->sendResponse([
                'imported' => $import->imported,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function signatory()
    {
        try {
            $company = BirSetting::forCurrentCompany();

            return $this->sendResponse([
                'signatory_name' => $company->signatory_name ?? null,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function export(Request $request, $id)
    {
        try {
            $bir = Bir2316::findOrFail($id);

            $withSignature = $request->boolean('with_signature');
            $signatoryName = $request->string('signatory_name')->trim()->value() ?: null;

            $pdfContent = $this->birPdfService->generate($bir, $withSignature, $signatoryName);

            $filename = 'BIR-2316-' . $bir->year . '-' . ($bir->tin ?? $bir->employee_id) . '.pdf';

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }
}
