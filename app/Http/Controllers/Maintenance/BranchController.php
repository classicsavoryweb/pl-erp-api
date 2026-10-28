<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\BranchExport;
use App\Exports\Maintenance\BranchesWorkbookExport;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\OtherCompany\OtherEmployee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

class BranchController extends Controller
{
    private array $oicDateAppointmentCache = [];
    private const OIC_DATE_APPOINTED_FIELDS = [
        "branch_head_date_appointed" => "branch_head_employee_number",
        "lab_head_date_appointed" => "lab_head_employee_number",
        "lab_head_2_date_appointed" => "lab_head_2_employee_number",
    ];

    private const ACCOUNT_ACTIVITY_FIELDS = [
        'expense_debit',
        'expense_credit',
        'advance_debit',
        'advance_credit',
        'ho_13th_debit',
        'ho_13th_debit_1',
        'ho_13th_credit',
        'branch_ho_13th_debit',
        'branch_ho_13th_debit_1',
        'branch_ho_13th_credit',
        'branch_ho_stat_debit',
        'branch_ho_stat_credit',
    ];

    private const OIC_HISTORY_POSITIONS = [
        [
            'label' => 'Branch OIC',
            'name_field' => 'branch_head',
            'employee_field' => 'branch_head_employee_number',
            'date_field' => 'branch_head_date_appointed',
        ],
        [
            'label' => 'Kitchen OIC',
            'name_field' => 'lab_head',
            'employee_field' => 'lab_head_employee_number',
            'date_field' => 'lab_head_date_appointed',
        ],
        [
            'label' => 'Kitchen OIC 2',
            'name_field' => 'lab_head_2',
            'employee_field' => 'lab_head_2_employee_number',
            'date_field' => 'lab_head_2_date_appointed',
        ],
    ];

    private function branchOicAssignmentQuery(string $employeeNumber)
    {
        return Branch::where("status", "active")
            ->where(function ($query) use ($employeeNumber) {
                $query->where("branch_head_employee_number", $employeeNumber)
                    ->orWhere("lab_head_employee_number", $employeeNumber)
                    ->orWhere("lab_head_2_employee_number", $employeeNumber);
            });
    }

    private function applyBranchFilters(Request $request, $branches)
    {
        if ($request->has(["column_type", "key_word"])) {
            $branches = $branches->where($request->column_type, "LIKE", "%" . $request->key_word . "%");
        }

        if ($request->has(["date_from", "date_to"])) {
            $branches = $branches->whereBetween('date_open', [$request->date_from, $request->date_to]);
        }

        if ($request->has(["status"])) {
            $branches = $branches->where("status", $request->status);
        }

        if ($request->has(["rate_code"])) {
            $branches = $branches->where("rate_code", $request->rate_code);
        }

        return $branches;
    }

    private function syncEmployeeOicStatuses(array $employeeNumbers, array $dateOverrides = []): void
    {
        $employeeNumbers = array_values(array_unique(array_filter($employeeNumbers)));

        if (empty($employeeNumbers)) {
            return;
        }

        foreach ($employeeNumbers as $employeeNumber) {
            $employee = Employee::where("employee_number", $employeeNumber)->first();

            if (!$employee) {
                continue;
            }

            $branchOicAssignment = $this->branchOicAssignmentQuery($employeeNumber)->first();

            $latestPosition = $employee->latest_position;
            $desiredDate = $dateOverrides[$employeeNumber] ?? null;

            if ($branchOicAssignment && $latestPosition) {
                $shouldUpdateBranchOic = $latestPosition->oic_assignment !== "Yes"
                    || !$latestPosition->date_appointed
                    || ($desiredDate && $latestPosition->date_appointed !== $desiredDate);

                if ($shouldUpdateBranchOic) {
                    $latestPosition->update([
                        "oic_assignment" => "Yes",
                        "date_appointed" => $desiredDate
                            ?: ($latestPosition->date_appointed ?: now()->format("Y-m-d")),
                    ]);
                    $employee->unsetRelation("latest_position");
                    $latestPosition = $employee->latest_position;
                }
            } elseif ($latestPosition && $latestPosition->oic_assignment === "Yes") {
                $latestPosition->update([
                    "oic_assignment" => "No",
                    "date_appointed" => null,
                ]);
                $employee->unsetRelation("latest_position");
                $latestPosition = $employee->latest_position;
            }

            $hasPositionOicAssignment = ($latestPosition?->oic_assignment ?? null) === "Yes";
            $newOicStatus = ($branchOicAssignment || $hasPositionOicAssignment) ? "OIC" : null;

            if ($employee->oic_status !== $newOicStatus) {
                $employee->update([
                    "oic_status" => $newOicStatus,
                ]);
            }
        }
    }

    private function getLoggedOicDateAppointed(?int $employeePositionId): ?string
    {
        if (!$employeePositionId) {
            return null;
        }

        $activities = Activity::query()
            ->where('subject_type', EmployeePosition::class)
            ->where('subject_id', $employeePositionId)
            ->whereIn('event', ['created', 'updated'])
            ->orderBy('id', 'desc')
            ->get();

        foreach ($activities as $activity) {
            $attributes = $activity->properties['attributes'] ?? [];
            $old = $activity->properties['old'] ?? [];

            if (($old['oic_assignment'] ?? null) === 'Yes' && !empty($old['date_appointed'])) {
                return $old['date_appointed'];
            }

            if (($attributes['oic_assignment'] ?? null) === 'Yes' && !empty($attributes['date_appointed'])) {
                return $attributes['date_appointed'];
            }
        }

        return null;
    }

    private function getEmployeeLatestOicDateAppointed(Employee $employee, bool $includeHistoricalFallback = false): ?string
    {
        $dateAppointed = $employee->latest_position?->date_appointed;

        if ($dateAppointed || !$includeHistoricalFallback) {
            return $dateAppointed;
        }

        return EmployeePosition::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('date_appointed')
            ->where('date_appointed', '<>', '')
            ->orderByDesc('date_started')
            ->orderByDesc('id')
            ->value('date_appointed')
            ?: $this->getLoggedOicDateAppointed($employee->latest_position?->id);
    }

    private function formatHistoryName(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        $name = preg_replace('/\s+/', ' ', trim($name));

        if ($name === '') {
            return null;
        }

        if (str_contains($name, ',')) {
            return mb_strtoupper($name, 'UTF-8');
        }

        $parts = explode(' ', $name);

        if (count($parts) < 2) {
            return mb_strtoupper($name, 'UTF-8');
        }

        $lastName = array_pop($parts);
        $firstAndMiddleName = implode(' ', $parts);

        return mb_strtoupper("{$lastName}, {$firstAndMiddleName}", 'UTF-8');
    }

    private function getOicDateAppointed(?string $employeeNumber, bool $includeHistoricalFallback = false): ?string
    {
        if (!$employeeNumber) {
            return null;
        }

        $cacheKey = $includeHistoricalFallback ? "historical:{$employeeNumber}" : $employeeNumber;

        if (!array_key_exists($cacheKey, $this->oicDateAppointmentCache)) {
            $employee = Employee::with("latest_position")
                ->where("employee_number", $employeeNumber)
                ->first();

            if ($employee) {
                $dateAppointed = $this->getEmployeeLatestOicDateAppointed(
                    $employee,
                    $includeHistoricalFallback
                );

                if (!$dateAppointed && $includeHistoricalFallback) {
                    $dateAppointed = $this->getLoggedOicDateAppointed($employee->latest_position?->id);
                }
            } else {
                $dateAppointed = null;
            }

            $this->oicDateAppointmentCache[$cacheKey] = $dateAppointed;
        }

        return $this->oicDateAppointmentCache[$cacheKey];
    }

    private function clearOicDateAppointmentCache(array $employeeNumbers = []): void
    {
        if (empty($employeeNumbers)) {
            $this->oicDateAppointmentCache = [];
            return;
        }

        foreach ($employeeNumbers as $employeeNumber) {
            if ($employeeNumber) {
                unset($this->oicDateAppointmentCache[$employeeNumber]);
                unset($this->oicDateAppointmentCache["historical:{$employeeNumber}"]);
            }
        }
    }

    private function captureOicDateSnapshot(Branch $branch): array
    {
        $snapshot = [];

        foreach (self::OIC_DATE_APPOINTED_FIELDS as $dateField => $employeeField) {
            $snapshot[$dateField] = $this->getOicDateAppointed($branch->{$employeeField});
        }

        return $snapshot;
    }

    private function appendOicDateSnapshotsToLatestUpdateActivity(int $branchId, array $oldSnapshot, array $newSnapshot): void
    {
        $activity = Activity::query()
            ->where('subject_type', Branch::class)
            ->where('subject_id', $branchId)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        if (!$activity) {
            return;
        }

        $properties = $activity->properties?->toArray() ?? [];
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];

        foreach (array_keys(self::OIC_DATE_APPOINTED_FIELDS) as $field) {
            $attributes[$field] = $newSnapshot[$field] ?? null;
            $old[$field] = $oldSnapshot[$field] ?? null;
        }

        $properties['attributes'] = $attributes;
        $properties['old'] = $old;

        $activity->properties = $properties;
        $activity->save();
    }

    private function appendOicDateAppointments($branches)
    {
        return $branches->map(function ($branch) {
            $branch->branch_head_date_appointed = $this->getOicDateAppointed($branch->branch_head_employee_number);
            $branch->lab_head_date_appointed = $this->getOicDateAppointed($branch->lab_head_employee_number);
            $branch->lab_head_2_date_appointed = $this->getOicDateAppointed($branch->lab_head_2_employee_number);

            return $branch;
        });
    }

    private function formatHistoryDate(?string $date): string
    {
        if (!$date) {
            return '-';
        }

        try {
            return Carbon::parse($date)->format("n/j/Y");
        } catch (\Throwable $th) {
            return (string) $date;
        }
    }

    private function appendHistoryEntry(array &$entries, ?string $name, ?string $date): void
    {
        if (!$name) {
            return;
        }

        $entry = sprintf(
            '%s (Date Appointed: %s)',
            $this->formatHistoryName($name),
            $this->formatHistoryDate($date)
        );

        if (!empty($entries) && end($entries) === $entry) {
            return;
        }

        $entries[] = $entry;
    }

    private function resolveOicHistoryDate(array $values, string $nameField, string $employeeField, string $dateField): ?string
    {
        return $values[$dateField]
            ?? $this->getOicDateAppointed($values[$employeeField] ?? null, true);
    }

    private function appendChangedOicHistoryEntry(array &$entries, array $old, array $attributes, string $nameField, string $employeeField, string $dateField): void
    {
        if (!array_key_exists($nameField, $attributes)) {
            return;
        }

        $oldName = $old[$nameField] ?? null;
        $newName = $attributes[$nameField] ?? null;

        if ($oldName && $oldName !== $newName) {
            $this->appendHistoryEntry(
                $entries,
                $oldName,
                $this->resolveOicHistoryDate($old, $nameField, $employeeField, $dateField)
            );
        }
    }

    private function getRoleHistoryEntries(Branch $branch, array $roleConfig): array
    {
        $entries = [];
        $nameField = $roleConfig['name_field'];
        $dateField = $roleConfig['date_field'];
        $employeeField = $roleConfig['employee_field'];
        $latestKnownName = null;

        $activities = Activity::query()
            ->where('subject_type', Branch::class)
            ->where('subject_id', $branch->id)
            ->whereIn('event', ['created', 'updated'])
            ->orderBy('id', 'asc')
            ->get();

        foreach ($activities as $activity) {
            $attributes = $activity->properties['attributes'] ?? [];
            $old = $activity->properties['old'] ?? [];

            if (array_key_exists($nameField, $attributes)) {
                $latestKnownName = $attributes[$nameField] ?: null;
            } elseif (array_key_exists($nameField, $old) && !$latestKnownName) {
                $latestKnownName = $old[$nameField] ?: null;
            }

            if ($activity->event === 'created') {
                $this->appendHistoryEntry(
                    $entries,
                    $attributes[$nameField] ?? null,
                    $this->resolveOicHistoryDate($attributes, $nameField, $employeeField, $dateField)
                );
                continue;
            }

            $this->appendChangedOicHistoryEntry($entries, $old, $attributes, $nameField, $employeeField, $dateField);

            if (array_key_exists($nameField, $attributes)) {
                $this->appendHistoryEntry(
                    $entries,
                    $attributes[$nameField] ?? null,
                    $this->resolveOicHistoryDate($attributes, $nameField, $employeeField, $dateField)
                );
                continue;
            }

            if (array_key_exists($dateField, $attributes) && $latestKnownName) {
                $this->appendHistoryEntry(
                    $entries,
                    $latestKnownName,
                    $attributes[$dateField] ?? null
                );
            }
        }

        $currentValues = [
            $nameField => $branch->{$nameField},
            $employeeField => $branch->{$employeeField},
            $dateField => $branch->{$dateField} ?? null,
        ];

        $this->appendHistoryEntry(
            $entries,
            $branch->{$nameField},
            $this->resolveOicHistoryDate($currentValues, $nameField, $employeeField, $dateField)
        );

        return $entries;
    }

    private function buildOicHistoryRows($branches): array
    {
        $rows = [];
        $maxHistoryColumns = 0;
        $this->clearOicDateAppointmentCache();

        foreach ($branches as $branch) {
            foreach (self::OIC_HISTORY_POSITIONS as $roleConfig) {
                $historyEntries = $this->getRoleHistoryEntries($branch, $roleConfig);
                $maxHistoryColumns = max($maxHistoryColumns, count($historyEntries));

                $rows[] = [
                    'branch' => $branch->name,
                    'branch_code' => $branch->short_name,
                    'status' => $branch->status,
                    'position' => $roleConfig['label'],
                    'history_entries' => $historyEntries,
                ];
            }
        }

        return [
            'rows' => $rows,
            'max_history_columns' => max(1, $maxHistoryColumns),
        ];
    }

    private function validateEditableOicDates(Request $request, Branch $branch): ?array
    {
        $this->clearOicDateAppointmentCache();
        $maxDate = Carbon::today()->addMonth()->format('Y-m-d');

        foreach (self::OIC_HISTORY_POSITIONS as $roleConfig) {
            $employeeField = $roleConfig['employee_field'];
            $dateField = $roleConfig['date_field'];

            $requestEmployee = $request->{$employeeField} ?: null;
            $requestDate = $request->{$dateField} ?: null;

            if ($requestDate) {
                try {
                    $requestDate = Carbon::parse($requestDate)->format('Y-m-d');
                } catch (\Throwable $th) {
                    $requestDate = (string) $requestDate;
                }
            }

            if ($requestDate && !$requestEmployee) {
                return ["message" => "Date appointed requires an assigned {$roleConfig['label']}."];
            }

            if (!$requestDate) {
                continue;
            }

            if ($requestDate > $maxDate) {
                return ["message" => "{$roleConfig['label']} date appointed cannot be greater than one month from today."];
            }
        }

        return null;
    }

    private function resolveEmployeeByNumber(?string $employeeNumber): ?array
    {
        if (!$employeeNumber) {
            return null;
        }

        $employee = Employee::where("employee_number", $employeeNumber)
            ->where("status", "active")
            ->first()
            ?? OtherEmployee::where("employee_number", $employeeNumber)
                ->where("status", "active")
                ->first()
            ?? Employee::where("employee_number", $employeeNumber)->first()
            ?? OtherEmployee::where("employee_number", $employeeNumber)->first();

        if ($employee) {
            return [
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
            ];
        }

        return null;
    }
    public function getAllBranches(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $branches = $this->applyBranchFilters($request, Branch::query());

            if ($request->has(["export"]) && $request->export) {
                $branches = $branches->get();
                $branches = $this->appendOicDateAppointments($branches);
                return Excel::download(new BranchExport($branches), "branches.xls");
            }
 
            $branches = $branches->paginate($perPage);
            $branches->setCollection($this->appendOicDateAppointments($branches->getCollection()));

            return $this->sendResponse($branches);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportBranches(Request $request) {
        try {
            $branches = $this->applyBranchFilters($request, Branch::query())
                ->get();
            $branches = $this->appendOicDateAppointments($branches);

            $oicHistory = $this->buildOicHistoryRows($branches);

            return Excel::download(
                new BranchesWorkbookExport(
                    $branches,
                    $oicHistory['rows'],
                    $oicHistory['max_history_columns']
                ),
                "branches.xls"
            );
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getBranches(Request $request) {
        try {
            $branches = Branch::where("status", "active")
            ->orderBy("name", "ASC")
            ->get();

            return $this->sendResponse(["branches" => $branches]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getFilterBranches(Request $request) {
        try {
            $branches = Branch::orderBy("name", "ASC")
            ->get();

            return $this->sendResponse(["branches" => $branches]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getBranchActivities($id)
    {
        try {
            $branch = Branch::findOrFail($id);

            $activities = Activity::with('causer')
                ->where('subject_type', Branch::class)
                ->where('subject_id', $branch->id)
                ->orderBy('created_at', 'DESC')
                ->get();

            $accountNumbers = $activities
                ->flatMap(function ($activity) {
                    $properties = collect([
                        $activity->properties['attributes'] ?? [],
                        $activity->properties['old'] ?? [],
                    ]);

                    return $properties
                        ->flatMap(function ($propertySet) {
                            return collect(self::ACCOUNT_ACTIVITY_FIELDS)
                                ->map(fn ($field) => $propertySet[$field] ?? null);
                        });
                })
                ->filter(fn ($value) => !is_null($value) && $value !== '')
                ->map(fn ($value) => (string) $value)
                ->unique()
                ->values();

            $accountNames = collect();
            if ($accountNumbers->isNotEmpty()) {
                try {
                    $accountNames = Account::query()
                        ->whereIn('acc_no', $accountNumbers)
                        ->pluck('acc_name', 'acc_no');
                } catch (Exception $exception) {
                    $accountNames = collect();
                }
            }

            return $this->sendResponse([
                'branch' => $branch,
                'activities' => $activities,
                'account_names' => $accountNames,
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createBranch(Request $request) {
        try {
            $request->validate([
                "name" => "required|unique:branches,name",
                "status" => "required",
                //"travel_time" => "required",
                //"device_name" => "required",
                "short_name" => "required|unique:branches,short_name",
                "type" => "required",
                "address" => "required",
                "location" => "required",
                "company" => "required",
                "date_open" => "required",
                "branch_email" => "required",
                "rate_code" => "required",
                'branch_head_employee_number' => "nullable|string",
                "branch_head_date_appointed" => "nullable|date",
                "area_head_employee_number" => "nullable|string",
                "lab_head_employee_number" => "nullable|string",
                "lab_head_date_appointed" => "nullable|date",
                "lab_head_2_employee_number" => "nullable|string",
                "lab_head_2_date_appointed" => "nullable|date",
                "mancom_employee_number" => "nullable|string",
                // "expense_debit" => "required",
                // "expense_credit" => "required",
                // "advance_debit" => "required",
                // "advance_credit" => "required",
                // "ho_13th_debit" => "required",
                // "ho_13th_debit_1" => "required",
                // "ho_13th_credit" => "required",
                // "branch_ho_13th_debit" => "required",
                // "branch_ho_13th_debit_1" => "required",
                // "branch_ho_13th_credit" => "required",
                // "branch_ho_stat_debit" => "required",
                // "branch_ho_stat_credit" => "required",
            ]);

            $count = Branch::count();
            $format = $request->company === "Photoline" ? "PL" : "SFI";
            $branchNumber = $format . str_pad(++$count, 5, '0', STR_PAD_LEFT);
            $bhEmployee = $this->resolveEmployeeByNumber($request->branch_head_employee_number);
            $ghEmployee = $this->resolveEmployeeByNumber($request->area_head_employee_number);
            $mancom = $this->resolveEmployeeByNumber($request->mancom_employee_number);
            $kitchenOIC = $this->resolveEmployeeByNumber($request->lab_head_employee_number);
            $kitchenOIC2 = $this->resolveEmployeeByNumber($request->lab_head_2_employee_number);

            if ($request->filled('branch_head_employee_number') && !$bhEmployee) {
                return $this->sendError(["message" => "Branch head employee number not found."]);
            }
            if ($request->filled('area_head_employee_number') && !$ghEmployee) {
                return $this->sendError(["message" => "Group head employee number not found."]);
            }
            if ($request->filled('mancom_employee_number') && !$mancom) {
                return $this->sendError(["message" => "Mancom employee number not found."]);
            }
            if ($request->filled('lab_head_employee_number') && !$kitchenOIC) {
                return $this->sendError(["message" => "Kitchen OIC employee number not found."]);
            }
            if ($request->filled('lab_head_2_employee_number') && !$kitchenOIC2) {
                return $this->sendError(["message" => "Kitchen OIC 2 employee number not found."]);
            }
            $dateOverrides = [];

            if (!empty($bhEmployee['employee_number']) && !empty($request->branch_head_date_appointed)) {
                $dateOverrides[$bhEmployee['employee_number']] = $request->branch_head_date_appointed;
            }
            if (!empty($kitchenOIC['employee_number']) && !empty($request->lab_head_date_appointed)) {
                $dateOverrides[$kitchenOIC['employee_number']] = $request->lab_head_date_appointed;
            }
            if (!empty($kitchenOIC2['employee_number']) && !empty($request->lab_head_2_date_appointed)) {
                $dateOverrides[$kitchenOIC2['employee_number']] = $request->lab_head_2_date_appointed;
            }

            $branch = Branch::create([
                "name" => $request->name,
                "branch_number" => $branchNumber,
                "status" => $request->status,
                "travel_time" => $request->travel_time,
                "device_name" => $request->device_name ?? null,
                "short_name" => $request->short_name,
                "type" => $request->type,
                "address" => $request->address,
                "location" => $request->location,
                "date_open" => $request->date_open,
                "company" => $request->company,
                "rate_code" => $request->rate_code,
                "branch_email" => $request->branch_email,
                'branch_head_employee_number' => $bhEmployee['employee_number'] ?? null,
                "area_head_employee_number" => $ghEmployee['employee_number'] ?? null,
                'branch_head' => $bhEmployee['full_name'] ?? null,
                "area_head" => $ghEmployee['full_name'] ?? null,
                "mancom_employee_number" => $mancom['employee_number'] ?? null,
                "mancom" => $mancom['full_name'] ?? null,
                "lab_head_employee_number" => $kitchenOIC['employee_number'] ?? null,
                "lab_head" => $kitchenOIC['full_name'] ?? null, 
                "lab_head_2_employee_number" => $kitchenOIC2['employee_number'] ?? null,
                "lab_head_2" => $kitchenOIC2['full_name'] ?? null,
                "expense_debit" => $request->expense_debit ?? null, 
                "expense_credit" => $request->expense_credit ?? null, 
                "advance_debit" => $request->advance_debit ?? null, 
                "advance_credit" => $request->advance_credit ?? null, 
                "ho_13th_debit" => $request->ho_13th_debit ?? null,
                "ho_13th_debit_1" => $request->ho_13th_debit_1 ?? null,
                "ho_13th_credit" => $request->ho_13th_credit ?? null,
                "branch_ho_13th_debit" => $request->branch_ho_13th_debit ?? null,
                "branch_ho_13th_debit_1" => $request->branch_ho_13th_debit_1 ?? null,
                "branch_ho_13th_credit" => $request->branch_ho_13th_credit ?? null,
                "branch_ho_stat_debit" => $request->branch_ho_stat_debit ?? null,
                "branch_ho_stat_credit" => $request->branch_ho_stat_credit ?? null,
            ]);

            $this->syncEmployeeOicStatuses([
                $branch->branch_head_employee_number,
                $branch->lab_head_employee_number,
                $branch->lab_head_2_employee_number,
            ], $dateOverrides);

            return $this->sendResponse(["message" => "Branch created successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editBranch(Request $request, $id) {
        try {
            $branch = Branch::findOrFail($id);
            $canEditAll = $request->user()->can("edit_branches_all");
            $canEditOic = $canEditAll || $request->user()->can("edit_branches_oic");

            if (!$canEditOic) {
                return $this->sendError(["message" => "You do not have permission to edit this branch."], 403);
            }

            $rules = [
                'branch_head_employee_number' => "nullable|string",
                "branch_head_date_appointed" => "nullable|date",
                "area_head_employee_number" => "nullable|string",
                "lab_head_employee_number" => "nullable|string",
                "lab_head_date_appointed" => "nullable|date",
                "lab_head_2_employee_number" => "nullable|string",
                "lab_head_2_date_appointed" => "nullable|date",
                "mancom_employee_number" => "nullable|string",
            ];

            if ($canEditAll) {
                $rules = array_merge([
                    "name" => "required|unique:branches,name,".$id,
                    "status" => "required",
                    "branch_number" => "required|unique:branches,branch_number,".$id,
                    "short_name" => "required|unique:branches,short_name,".$id,
                    "type" => "required",
                    "address" => "required",
                    "location" => "required",
                    "company" => "required",
                    "date_open" => "required",
                    "branch_email" => "nullable",
                    "rate_code" => "required",
                ], $rules);
            }

            $request->validate($rules);

            $bhEmployee = $this->resolveEmployeeByNumber($request->branch_head_employee_number);
            $ghEmployee = $this->resolveEmployeeByNumber($request->area_head_employee_number);
            $mancom = $this->resolveEmployeeByNumber($request->mancom_employee_number);
            $kitchenOIC = $this->resolveEmployeeByNumber($request->lab_head_employee_number);
            $kitchenOIC2 = $this->resolveEmployeeByNumber($request->lab_head_2_employee_number);

            if ($request->filled('branch_head_employee_number') && !$bhEmployee) {
                return $this->sendError(["message" => "Branch head employee number not found."]);
            }
            if ($request->filled('area_head_employee_number') && !$ghEmployee) {
                return $this->sendError(["message" => "Group head employee number not found."]);
            }
            if ($request->filled('mancom_employee_number') && !$mancom) {
                return $this->sendError(["message" => "Mancom employee number not found."]);
            }
            if ($request->filled('lab_head_employee_number') && !$kitchenOIC) {
                return $this->sendError(["message" => "Kitchen OIC employee number not found."]);
            }
            if ($request->filled('lab_head_2_employee_number') && !$kitchenOIC2) {
                return $this->sendError(["message" => "Kitchen OIC 2 employee number not found."]);
            }
            $dateValidationError = $this->validateEditableOicDates($request, $branch);
            if ($dateValidationError) {
                return $this->sendError($dateValidationError);
            }
            $this->clearOicDateAppointmentCache();
            $oldOicDateSnapshot = $this->captureOicDateSnapshot($branch);
            $affectedEmployeeNumbers = [
                $branch->branch_head_employee_number,
                $branch->lab_head_employee_number,
                $branch->lab_head_2_employee_number,
                $bhEmployee['employee_number'] ?? null,
                $kitchenOIC['employee_number'] ?? null,
                $kitchenOIC2['employee_number'] ?? null,
            ];
            $dateOverrides = [];

            if (!empty($bhEmployee['employee_number']) && !empty($request->branch_head_date_appointed)) {
                $dateOverrides[$bhEmployee['employee_number']] = $request->branch_head_date_appointed;
            }
            if (!empty($kitchenOIC['employee_number']) && !empty($request->lab_head_date_appointed)) {
                $dateOverrides[$kitchenOIC['employee_number']] = $request->lab_head_date_appointed;
            }
            if (!empty($kitchenOIC2['employee_number']) && !empty($request->lab_head_2_date_appointed)) {
                $dateOverrides[$kitchenOIC2['employee_number']] = $request->lab_head_2_date_appointed;
            }

            $updateData = [
                'branch_head_employee_number' => $bhEmployee['employee_number'] ?? null,
                "area_head_employee_number" => $ghEmployee['employee_number'] ?? null,
                'branch_head' => $bhEmployee['full_name'] ?? null,
                "area_head" => $ghEmployee['full_name'] ?? null,
                "mancom_employee_number" => $mancom['employee_number'] ?? null,
                "mancom" => $mancom['full_name'] ?? null,
                "lab_head_employee_number" => $kitchenOIC['employee_number'] ?? null,
                "lab_head" => $kitchenOIC['full_name'] ?? null,
                "lab_head_2_employee_number" => $kitchenOIC2['employee_number'] ?? null,
                "lab_head_2" => $kitchenOIC2['full_name'] ?? null,
            ];

            if ($canEditAll) {
                $updateData = array_merge([
                    "name" => $request->name,
                    "status" => $request->status,
                    "travel_time" => $request->travel_time,
                    "device_name" => $request->device_name ?? null,
                    "short_name" => $request->short_name,
                    "branch_number" => $request->branch_number,
                    "type" => $request->type,
                    "address" => $request->address,
                    "location" => $request->location,
                    "date_open" => $request->date_open,
                    "company" => $request->company,
                    "rate_code" => $request->rate_code,
                    "branch_email" => $request->branch_email,
                    "expense_debit" => $request->expense_debit ?? null, 
                    "expense_credit" => $request->expense_credit ?? null, 
                    "advance_debit" => $request->advance_debit ?? null, 
                    "advance_credit" => $request->advance_credit ?? null,
                    "ho_13th_debit" => $request->ho_13th_debit ?? null,
                    "ho_13th_debit_1" => $request->ho_13th_debit_1 ?? null,
                    "ho_13th_credit" => $request->ho_13th_credit ?? null,
                    "branch_ho_13th_debit" => $request->branch_ho_13th_debit ?? null,
                    "branch_ho_13th_debit_1" => $request->branch_ho_13th_debit_1 ?? null,
                    "branch_ho_13th_credit" => $request->branch_ho_13th_credit ?? null,
                    "branch_ho_stat_debit" => $request->branch_ho_stat_debit ?? null,
                    "branch_ho_stat_credit" => $request->branch_ho_stat_credit ?? null,
                ], $updateData);
            }

            $branch->update($updateData);

            $effectiveDateOverrides = $branch->status === "active" ? $dateOverrides : [];
            $this->syncEmployeeOicStatuses($affectedEmployeeNumbers, $effectiveDateOverrides);
            $branch->refresh();

            $this->clearOicDateAppointmentCache($affectedEmployeeNumbers);
            $newOicDateSnapshot = $this->captureOicDateSnapshot($branch);
            $this->appendOicDateSnapshotsToLatestUpdateActivity($branch->id, $oldOicDateSnapshot, $newOicDateSnapshot);

            return $this->sendResponse(["message" => "Branch updated successfully"]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
