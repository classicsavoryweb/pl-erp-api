<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\BranchExport;
use App\Exports\Maintenance\BranchesWorkbookExport;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Branch;
use App\Models\Employee;
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
        "kitchen_oic_date_appointed" => "kitchen_oic_employee_number",
        "kitchen_oic_2_date_appointed" => "kitchen_oic_2_employee_number",
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
            'name_field' => 'kitchen_oic',
            'employee_field' => 'kitchen_oic_employee_number',
            'date_field' => 'kitchen_oic_date_appointed',
        ],
        [
            'label' => 'Kitchen OIC 2',
            'name_field' => 'kitchen_oic_2',
            'employee_field' => 'kitchen_oic_2_employee_number',
            'date_field' => 'kitchen_oic_2_date_appointed',
        ],
    ];

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

            $branchOicAssignment = Branch::where(function ($query) use ($employeeNumber) {
                $query->where("branch_head_employee_number", $employeeNumber)
                    ->orWhere("kitchen_oic_employee_number", $employeeNumber)
                    ->orWhere("kitchen_oic_2_employee_number", $employeeNumber);
            })->first();

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

    private function getOicDateAppointed(?string $employeeNumber): ?string
    {
        if (!$employeeNumber) {
            return null;
        }

        if (!array_key_exists($employeeNumber, $this->oicDateAppointmentCache)) {
            $employee = Employee::with("latest_position")
                ->where("employee_number", $employeeNumber)
                ->first();

            $this->oicDateAppointmentCache[$employeeNumber] = $employee?->latest_position?->date_appointed;
        }

        return $this->oicDateAppointmentCache[$employeeNumber];
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
            $branch->kitchen_oic_date_appointed = $this->getOicDateAppointed($branch->kitchen_oic_employee_number);
            $branch->kitchen_oic_2_date_appointed = $this->getOicDateAppointed($branch->kitchen_oic_2_employee_number);

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
            $name,
            $this->formatHistoryDate($date)
        );

        if (!empty($entries) && end($entries) === $entry) {
            return;
        }

        $entries[] = $entry;
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
                    $attributes[$dateField] ?? null
                );
                continue;
            }

            if (array_key_exists($nameField, $attributes)) {
                $this->appendHistoryEntry(
                    $entries,
                    $attributes[$nameField] ?? null,
                    $attributes[$dateField] ?? null
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

        $this->appendHistoryEntry(
            $entries,
            $branch->{$nameField},
            $this->getOicDateAppointed($branch->{$employeeField})
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
        $today = Carbon::today()->format('Y-m-d');

        foreach (self::OIC_HISTORY_POSITIONS as $roleConfig) {
            $employeeField = $roleConfig['employee_field'];
            $dateField = $roleConfig['date_field'];

            $requestEmployee = $request->{$employeeField} ?: null;
            $requestDate = $request->{$dateField} ?: null;

            if ($requestDate && !$requestEmployee) {
                return ["message" => "Date appointed requires an assigned {$roleConfig['label']}."];
            }

            if (!$requestDate) {
                continue;
            }

            if ($requestDate < $today) {
                $currentEmployee = $branch->{$employeeField} ?: null;
                $currentDate = $this->getOicDateAppointed($currentEmployee);
                $isUnchangedLegacyValue = $requestEmployee === $currentEmployee
                    && $requestDate === $currentDate;

                if (!$isUnchangedLegacyValue) {
                    return ["message" => "{$roleConfig['label']} date appointed cannot be before today."];
                }
            }
        }

        return null;
    }

    private function resolveEmployeeByNumber(?string $employeeNumber): ?array
    {
        if (!$employeeNumber) {
            return null;
        }

        $employee = Employee::where("employee_number", $employeeNumber)->first();
        if ($employee) {
            return [
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
            ];
        }

        $otherEmployee = OtherEmployee::where("employee_number", $employeeNumber)
            ->orWhere("employee_number_2", $employeeNumber)
            ->first();

        if ($otherEmployee) {
            return [
                'employee_number' => $otherEmployee->employee_number,
                'full_name' => $otherEmployee->full_name,
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
                "group_head_employee_number" => "nullable|string",
                "kitchen_oic_employee_number" => "nullable|string",
                "kitchen_oic_2_employee_number" => "nullable|string",
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
            $format = $request->company === "Tony Ting Food Concepts Inc." ? "TTFC" : "SFI";
            $branchNumber = $format . str_pad(++$count, 5, '0', STR_PAD_LEFT);
            $bhEmployee = $this->resolveEmployeeByNumber($request->branch_head_employee_number);
            $ghEmployee = $this->resolveEmployeeByNumber($request->group_head_employee_number);
            $mancom = $this->resolveEmployeeByNumber($request->mancom_employee_number);
            $kitchenOIC = $this->resolveEmployeeByNumber($request->kitchen_oic_employee_number);
            $kitchenOIC2 = $this->resolveEmployeeByNumber($request->kitchen_oic_2_employee_number);

            if ($request->filled('branch_head_employee_number') && !$bhEmployee) {
                return $this->sendError(["message" => "Branch head employee number not found."]);
            }
            if ($request->filled('group_head_employee_number') && !$ghEmployee) {
                return $this->sendError(["message" => "Group head employee number not found."]);
            }
            if ($request->filled('mancom_employee_number') && !$mancom) {
                return $this->sendError(["message" => "Mancom employee number not found."]);
            }
            if ($request->filled('kitchen_oic_employee_number') && !$kitchenOIC) {
                return $this->sendError(["message" => "Kitchen OIC employee number not found."]);
            }
            if ($request->filled('kitchen_oic_2_employee_number') && !$kitchenOIC2) {
                return $this->sendError(["message" => "Kitchen OIC 2 employee number not found."]);
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
                "group_head_employee_number" => $ghEmployee['employee_number'] ?? null,
                'branch_head' => $bhEmployee['full_name'] ?? null,
                "group_head" => $ghEmployee['full_name'] ?? null,
                "mancom_employee_number" => $mancom['employee_number'] ?? null,
                "mancom" => $mancom['full_name'] ?? null,
                "kitchen_oic_employee_number" => $kitchenOIC['employee_number'] ?? null,
                "kitchen_oic" => $kitchenOIC['full_name'] ?? null, 
                "kitchen_oic_2_employee_number" => $kitchenOIC2['employee_number'] ?? null,
                "kitchen_oic_2" => $kitchenOIC2['full_name'] ?? null,
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
                $branch->kitchen_oic_employee_number,
                $branch->kitchen_oic_2_employee_number,
            ], []);

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
                "group_head_employee_number" => "nullable|string",
                "kitchen_oic_employee_number" => "nullable|string",
                "kitchen_oic_date_appointed" => "nullable|date",
                "kitchen_oic_2_employee_number" => "nullable|string",
                "kitchen_oic_2_date_appointed" => "nullable|date",
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
                    "branch_email" => "required",
                    "rate_code" => "required",
                ], $rules);
            }

            $request->validate($rules);

            $bhEmployee = $this->resolveEmployeeByNumber($request->branch_head_employee_number);
            $ghEmployee = $this->resolveEmployeeByNumber($request->group_head_employee_number);
            $mancom = $this->resolveEmployeeByNumber($request->mancom_employee_number);
            $kitchenOIC = $this->resolveEmployeeByNumber($request->kitchen_oic_employee_number);
            $kitchenOIC2 = $this->resolveEmployeeByNumber($request->kitchen_oic_2_employee_number);

            if ($request->filled('branch_head_employee_number') && !$bhEmployee) {
                return $this->sendError(["message" => "Branch head employee number not found."]);
            }
            if ($request->filled('group_head_employee_number') && !$ghEmployee) {
                return $this->sendError(["message" => "Group head employee number not found."]);
            }
            if ($request->filled('mancom_employee_number') && !$mancom) {
                return $this->sendError(["message" => "Mancom employee number not found."]);
            }
            if ($request->filled('kitchen_oic_employee_number') && !$kitchenOIC) {
                return $this->sendError(["message" => "Kitchen OIC employee number not found."]);
            }
            if ($request->filled('kitchen_oic_2_employee_number') && !$kitchenOIC2) {
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
                $branch->kitchen_oic_employee_number,
                $branch->kitchen_oic_2_employee_number,
                $bhEmployee['employee_number'] ?? null,
                $kitchenOIC['employee_number'] ?? null,
                $kitchenOIC2['employee_number'] ?? null,
            ];
            $dateOverrides = [];

            if (!empty($bhEmployee['employee_number']) && !empty($request->branch_head_date_appointed)) {
                $dateOverrides[$bhEmployee['employee_number']] = $request->branch_head_date_appointed;
            }
            if (!empty($kitchenOIC['employee_number']) && !empty($request->kitchen_oic_date_appointed)) {
                $dateOverrides[$kitchenOIC['employee_number']] = $request->kitchen_oic_date_appointed;
            }
            if (!empty($kitchenOIC2['employee_number']) && !empty($request->kitchen_oic_2_date_appointed)) {
                $dateOverrides[$kitchenOIC2['employee_number']] = $request->kitchen_oic_2_date_appointed;
            }

            $updateData = [
                'branch_head_employee_number' => $bhEmployee['employee_number'] ?? null,
                "group_head_employee_number" => $ghEmployee['employee_number'] ?? null,
                'branch_head' => $bhEmployee['full_name'] ?? null,
                "group_head" => $ghEmployee['full_name'] ?? null,
                "mancom_employee_number" => $mancom['employee_number'] ?? null,
                "mancom" => $mancom['full_name'] ?? null,
                "kitchen_oic_employee_number" => $kitchenOIC['employee_number'] ?? null,
                "kitchen_oic" => $kitchenOIC['full_name'] ?? null,
                "kitchen_oic_2_employee_number" => $kitchenOIC2['employee_number'] ?? null,
                "kitchen_oic_2" => $kitchenOIC2['full_name'] ?? null,
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

            $this->syncEmployeeOicStatuses($affectedEmployeeNumbers, $dateOverrides);
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
