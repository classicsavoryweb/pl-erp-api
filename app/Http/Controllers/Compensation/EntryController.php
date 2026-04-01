<?php

namespace App\Http\Controllers\Compensation;

use App\Exports\Compensation\EntryExport;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateEntry;
use App\Models\Branch;
use App\Models\Entry;
use App\Models\Payroll;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use DB;
use Illuminate\Support\Facades\Auth;
use Str;

class EntryController extends Controller
{
    private function getEntryBranchAliasGroups(): array
    {
        return [
            [
                'canonical_name' => 'Operations Department - Branch',
                'canonical_short_name' => 'OPD',
                'canonical_branch_number' => 'TTFC00035',
                'branch_numbers' => ['TTFC00035', 'SFI00190'],
                'keywords' => [
                    'operations department - branch',
                    'operations department - branch 4',
                    'opd',
                    'opd4',
                ],
            ],
            [
                'canonical_name' => 'Operations Department - Ho',
                'canonical_short_name' => 'OPD2',
                'canonical_branch_number' => 'SFI00122',
                'branch_numbers' => ['SFI00122'],
                'keywords' => [
                    'operations department - ho',
                    'opd2',
                    'sfi00122',
                ],
            ],
            [
                'canonical_name' => 'Operations Department - Branch',
                'canonical_short_name' => 'OPD',
                'canonical_branch_number' => 'SFI00010',
                'branch_numbers' => ['SFI00010', 'SFI00191'],
                'keywords' => [
                    'operations department - branch',
                    'operations department - branch 3',
                    'opd',
                    'opd3',
                    'sfi00010',
                    'sfi00191',
                ],
            ],
        ];
    }

    private function getEntryAliasGroupForBranch(?Branch $branch): ?array
    {
        if (!$branch) {
            return null;
        }

        foreach ($this->getEntryBranchAliasGroups() as $group) {
            if (in_array($branch->branch_number, $group['branch_numbers'], true)) {
                return $group;
            }
        }

        return null;
    }

    private function getEntryAliasGroupForKeyword(?string $keyword): ?array
    {
        if (!$keyword) {
            return null;
        }

        $normalized = Str::lower(trim($keyword));

        foreach ($this->getEntryBranchAliasGroups() as $group) {
            if (Str::contains($normalized, $group['keywords'])) {
                return $group;
            }
        }

        return null;
    }

    private function getEntryAliasBranches(array $group)
    {
        return Branch::query()
            ->whereIn('branch_number', $group['branch_numbers'])
            ->get(['id', 'name', 'short_name', 'branch_number']);
    }

    private function applyEntryBranchAliases($entry)
    {
        $branch = $entry->branch;

        if (!$branch) {
            return $entry;
        }

        $aliasGroup = $this->getEntryAliasGroupForBranch($branch);

        if ($aliasGroup) {
            $branch = clone $branch;
            $branch->name = $aliasGroup['canonical_name'];
            if (!empty($aliasGroup['canonical_short_name'])) {
                $branch->short_name = $aliasGroup['canonical_short_name'];
            }
            $branch->branch_number = $aliasGroup['canonical_branch_number'];
            $entry->setRelation('branch', $branch);
            $entry->branch_number = $aliasGroup['canonical_branch_number'];
        }

        return $entry;
    }

    public function getAllEntries(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $entryQuery = Entry::with(['branch']);

            if (!$request->boolean('include_non_payroll')) {
                $entryQuery->whereNotNull('pay_date');
            }

            if ($request->filled("branch_id") || $request->filled("branch_number")) {
                $branch = null;
                if ($request->filled("branch_id")) {
                    $branch = Branch::find($request->branch_id);
                }
                if (!$branch && $request->filled("branch_number")) {
                    $branch = Branch::where("branch_number", $request->branch_number)->first();
                }

                $aliasGroup = $this->getEntryAliasGroupForBranch($branch);
                $aliasBranches = $aliasGroup
                    ? $this->getEntryAliasBranches($aliasGroup)
                    : collect();

                $entryQuery->where(function($query) use ($request, $branch, $aliasBranches) {
                    if ($aliasBranches->isNotEmpty()) {
                        $query->orWhereIn("branch_id", $aliasBranches->pluck('id')->all())
                            ->orWhereIn("branch_number", $aliasBranches->pluck('branch_number')->all());

                        foreach ($aliasBranches->pluck('short_name')->filter() as $shortName) {
                            $query->orWhere("document_no", "LIKE", $shortName . "|%");
                        }

                        return;
                    }

                    if ($request->filled("branch_id")) {
                        $query->orWhere("branch_id", $request->branch_id);
                    }
                    if ($request->filled("branch_number")) {
                        $query->orWhere("branch_number", $request->branch_number);
                    }
                    if ($branch && $branch->short_name) {
                        $query->orWhere("document_no", "LIKE", $branch->short_name . "|%");
                    }
                });
            }

            if ($request->filled("cutoff_start") && $request->filled("cutoff_end")) {
                $cutoffStart = $request->cutoff_start;
                $cutoffEnd = $request->cutoff_end;
                if ($request->filled("year")) {
                    $dates = $this->normalizeCutOff($cutoffStart, $cutoffEnd, $request->year);
                    $normalizedCutoffStart = $dates["start_date"];
                    $normalizedCutoffEnd = $dates["end_date"];
                    $cutoffStartSlash = Carbon::parse($normalizedCutoffStart)->format("m/d/Y");
                    $cutoffEndSlash = Carbon::parse($normalizedCutoffEnd)->format("m/d/Y");

                    $cutoffPayroll = Payroll::query()
                        ->whereDate("start_date", $normalizedCutoffStart)
                        ->whereDate("end_date", $normalizedCutoffEnd)
                        ->orderBy("pay_date", "DESC")
                        ->first();

                    $cutoffPayDate = $cutoffPayroll?->pay_date;

                    $entryQuery->where(function($query) use ($normalizedCutoffStart, $cutoffStartSlash, $normalizedCutoffEnd, $cutoffEndSlash, $cutoffPayDate) {
                        $query
                            ->where(function($inner) use ($normalizedCutoffStart, $cutoffStartSlash) {
                                $inner->where("document_no", "LIKE", "%{$normalizedCutoffStart}%")
                                      ->orWhere("document_no", "LIKE", "%{$cutoffStartSlash}%");
                            })
                            ->where(function($inner) use ($normalizedCutoffEnd, $cutoffEndSlash) {
                                $inner->where("document_no", "LIKE", "%{$normalizedCutoffEnd}%")
                                      ->orWhere("document_no", "LIKE", "%{$cutoffEndSlash}%");
                            });

                        if ($cutoffPayDate) {
                            $query->orWhereDate("pay_date", $cutoffPayDate);
                        }
                    });
                } else {
                    $cutoffStartSlash = str_replace("-", "/", $cutoffStart);
                    $cutoffEndSlash = str_replace("-", "/", $cutoffEnd);

                    $cutoffPayroll = Payroll::query()
                        ->whereRaw("DATE_FORMAT(start_date, '%m-%d') = ?", [$cutoffStart])
                        ->whereRaw("DATE_FORMAT(end_date, '%m-%d') = ?", [$cutoffEnd])
                        ->orderBy("pay_date", "DESC")
                        ->first();

                    $cutoffPayDate = $cutoffPayroll?->pay_date;

                    $entryQuery->where(function($query) use ($cutoffStart, $cutoffStartSlash, $cutoffEnd, $cutoffEndSlash, $cutoffPayDate) {
                        $query
                            ->where(function($inner) use ($cutoffStart, $cutoffStartSlash) {
                                $inner->where("document_no", "LIKE", "%{$cutoffStart}%")
                                      ->orWhere("document_no", "LIKE", "%{$cutoffStartSlash}%");
                            })
                            ->where(function($inner) use ($cutoffEnd, $cutoffEndSlash) {
                                $inner->where("document_no", "LIKE", "%{$cutoffEnd}%")
                                      ->orWhere("document_no", "LIKE", "%{$cutoffEndSlash}%");
                            });

                        if ($cutoffPayDate) {
                            $query->orWhereDate("pay_date", $cutoffPayDate);
                        }
                    });
                }
            }
             
            if ($request->has(["column_type", "key_word"])) {
                if ($request->column_type === "branch.name") {
                    $entryQuery->whereHas('branch', function($query) use ($request) {
                        $aliasGroup = $this->getEntryAliasGroupForKeyword($request->key_word);
                        if ($aliasGroup) {
                            $query->whereIn('branch_number', $aliasGroup['branch_numbers']);
                            return;
                        }

                        $query->where('name', 'LIKE', "%{$request->key_word}%");
                    });
                } else {
                    $entryQuery->where($request->column_type, 'LIKE', "%{$request->key_word}%");
                }
            }

            if ($request->has(["export"]) && $request->export) {
                $entry = $entryQuery->get()->map(function ($item) {
                    return $this->applyEntryBranchAliases($item);
                });
                return Excel::download(new EntryExport($entry), "entry.xlsx", \Maatwebsite\Excel\Excel::XLSX);
            }

            $entry = $entryQuery->paginate($perPage);
            $entry->setCollection(
                $entry->getCollection()->map(function ($item) {
                    return $this->applyEntryBranchAliases($item);
                })
            );

            return $this->sendResponse($entry);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function generateEntries(Request $request) {
        try {
            $request->validate([
                "date_from" => "required",
                "date_to" => "required",
                "send_to_apsys" => "nullable|boolean",
            ]);

            $pay_date = $request->pay_date;
            if (!$pay_date) {
                $payrollDate = Payroll::where("start_date", $request->date_from)
                    ->where("end_date", $request->date_to)
                    ->orderBy("pay_date", "DESC")
                    ->first();

                if (!$payrollDate) {
                    return $this->sendError(["message" => "No payroll found for the selected cutoff."]);
                }

                $pay_date = $payrollDate->pay_date;
            }

            $payroll = Payroll::with(["employee"])
            ->where("pay_date", $pay_date)
            ->where("start_date", $request->date_from)
            ->where("end_date", $request->date_to)
            ->get();

            $start_date = $request->date_from;
            $end_Date = $request->date_to;
            $sendToApsys = $request->boolean("send_to_apsys", true);

            GenerateEntry::dispatch($payroll, $pay_date, $start_date, $end_Date, Auth::user()->id, $sendToApsys);

            return $this->sendResponse(["message" => "Entry are in queue for processing."]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllEntries(Request $request) {
        $request["export"] = true;

        return $this->getAllEntries($request);
    }
}
