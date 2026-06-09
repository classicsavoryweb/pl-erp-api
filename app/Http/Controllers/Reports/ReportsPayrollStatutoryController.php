<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll;
use App\Models\SSS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

class ReportsPayrollStatutoryController extends Controller
{
    public function exportPayrollStatutory(Request $request)
    {
        try {
            $validated = $request->validate([
                'year' => ['required', 'integer', 'min:2000'],
                'month' => ['required', 'integer', 'min:1', 'max:12'],
            ]);

            $templateRelativePath = 'templates/statutory-payroll-template.xlsx';
            if (!Storage::disk('hr_system_folder')->exists($templateRelativePath)) {
                return $this->sendError(['message' => 'Statutory payroll template not found.']);
            }
            $templatePath = Storage::disk('hr_system_folder')->path($templateRelativePath);

            $rangeEnd = Carbon::createFromDate($validated['year'], $validated['month'], 25)->startOfDay();
            $rangeStart = $rangeEnd->copy()->subMonth()->day(26)->startOfDay();

            $payrolls = Payroll::with(['employee', 'employee.current_branch', 'employee.home_branch', 'employee.payroll_list', 'payroll_list', 'timekeeping_branch', 'employee.current_position', 'details'])
                ->whereBetween('start_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                ->get();

            $payrollGroups = $payrolls
                ->groupBy('employee_id')
                ->sortBy(function ($employeePayrolls) {
                    $payroll = $employeePayrolls->first();
                    $employee = $payroll?->employee;
                    $branch = $employeePayrolls->pluck('timekeeping_branch')->filter()->first()
                        ?? $employee?->current_branch;
                    $branchCode = $branch?->short_name ?? '';
                    $lastName = $employee?->last_name ?? '';
                    $firstName = $employee?->first_name ?? '';
                    return sprintf('%s|%s|%s', $branchCode, $lastName, $firstName);
                })
                ->values();
            $summaryBranches = Branch::query()
                ->where('status', 'active')
                ->whereNotNull('short_name')
                ->orderBy('short_name')
                ->pluck('short_name')
                ->filter()
                ->unique()
                ->values();

            $spreadsheet = IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();
            $startRow = 5;
            $maxRow = $sheet->getHighestRow();
            $templateTotalsRow = null;
            $summaryRowStyleSource = $sheet->getStyle("AF{$startRow}:AJ{$startRow}");
            $summaryTotalTemplateRow = null;
            $summaryTotalStyleSource = null;

            for ($r = $maxRow; $r >= $startRow; $r--) {
                $value = $sheet->getCell("I{$r}")->getValue();
                if (is_string($value) && str_contains($value, 'SUM(I5')) {
                    $templateTotalsRow = $r;
                    break;
                }
            }

            for ($r = $startRow; $r <= $maxRow; $r++) {
                $value = $sheet->getCell("AF{$r}")->getValue();
                if (is_string($value) && strtoupper(trim($value)) === 'TOTAL') {
                    $summaryTotalTemplateRow = $r;
                    $summaryTotalStyleSource = $sheet->getStyle("AF{$r}:AJ{$r}");
                    break;
                }
            }

            if ($templateTotalsRow === null) {
                $templateTotalsRow = $maxRow + 1;
            }

            $headerLabel = 'Statutory Report (' . Carbon::createFromDate($validated['year'], $validated['month'], 1)->format('F Y') . ')';
            $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
            $searchMaxRow = min($maxRow, 10);
            for ($r = 1; $r <= $searchMaxRow; $r++) {
                for ($c = 1; $c <= $highestColumnIndex; $c++) {
                    $cell = $sheet->getCellByColumnAndRow($c, $r);
                    $value = $cell->getValue();
                    if (is_string($value) && str_contains($value, 'Statutory Report')) {
                        $cell->setValueExplicit($headerLabel, DataType::TYPE_STRING);
                        break 2;
                    }
                }
            }

            $maxDataRows = $templateTotalsRow - $startRow;
            $requiredRows = max($payrollGroups->count(), $summaryBranches->count());
            $templateRow = $startRow;

            if ($requiredRows > $maxDataRows) {
                $additionalRows = $requiredRows - $maxDataRows;
                $insertAt = $templateTotalsRow;
                $sheet->insertNewRowBefore($insertAt, $additionalRows);

                $rowHeight = $sheet->getRowDimension($templateRow)->getRowHeight();
                $newMaxRow = $maxRow + $additionalRows;
                $templateTotalsRow += $additionalRows;

                $insertEnd = $insertAt + $additionalRows - 1;

                for ($r = $insertAt; $r <= $insertEnd; $r++) {
                    if ($rowHeight > 0) {
                        $sheet->getRowDimension($r)->setRowHeight($rowHeight);
                    }
                    $sheet->duplicateStyle(
                        $sheet->getStyle("A{$templateRow}:AJ{$templateRow}"),
                        "A{$r}:AJ{$r}"
                    );

                    $sheet->setCellValue("K{$r}", "=SUM(I{$r}+J{$r})");
                    $sheet->setCellValue("N{$r}", "=SUM(K{$r}+L{$r}+M{$r})");
                    $sheet->setCellValue("S{$r}", "=SUM(Q{$r}+R{$r})");
                    $sheet->setCellValue("V{$r}", "=SUM(T{$r}+U{$r})");
                    $sheet->setCellValue("Y{$r}", "=SUM(W{$r}+X{$r})");
                    $sheet->setCellValue("Z{$r}", "=SUM(P{$r}+T{$r}+W{$r})");
                    $sheet->setCellValue("AG{$r}", "=SUMIF(D:D,AF:AF,S:S)");
                    $sheet->setCellValue("AH{$r}", "=SUMIF(D:D,AF:AF,U:U)");
                    $sheet->setCellValue("AI{$r}", "=SUMIF(D:D,AF:AF,X:X)");
                    $sheet->setCellValue("AJ{$r}", "=SUM(AG{$r}:AI{$r})");
                }

                $maxRow = $newMaxRow;
                $maxDataRows = $maxRow - $startRow + 1;
            }

            $inputColumns = [
                'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
                'I', 'J', 'L', 'M', 'O',
                'P', 'Q', 'R', 'T', 'U', 'W', 'X',
                'AA', 'AB',
            ];

            for ($row = $startRow; $row <= $maxRow; $row++) {
                foreach ($inputColumns as $col) {
                    $sheet->setCellValue($col . $row, null);
                }
                $sheet->setCellValue('AF' . $row, null);
            }

            $row = $startRow;
            foreach ($payrollGroups as $employeePayrolls) {
                if ($row > $maxRow) {
                    break;
                }

                $nonMaternityPayrolls = $employeePayrolls->filter(function ($employeePayroll) {
                    return !(bool) ($employeePayroll?->details?->is_on_maternity_leave
                        ?? $employeePayroll?->is_maternity
                        ?? $employeePayroll?->payroll_list?->is_on_maternity_leave
                        ?? false);
                });
                $payrollsForDisplay = $nonMaternityPayrolls->isNotEmpty()
                    ? $nonMaternityPayrolls
                    : collect();
                $payroll = ($payrollsForDisplay->isNotEmpty() ? $payrollsForDisplay : $employeePayrolls)
                    ->sortByDesc('start_date')
                    ->first();
                $employee = $payroll?->employee;
                $branch = $payroll?->timekeeping_branch ?? $employee?->current_branch;
                $branchCode = $branch?->short_name ?? '';
                $region = $employee?->home_branch?->location ?? $branch?->location ?? '';
                $hasMaternityPayroll = $employeePayrolls->contains(function ($employeePayroll) {
                    return (bool) ($employeePayroll?->details?->is_on_maternity_leave
                        ?? $employeePayroll?->is_maternity
                        ?? $employeePayroll?->payroll_list?->is_on_maternity_leave
                        ?? false);
                });
                $isContributionOnly = $hasMaternityPayroll && $payrollsForDisplay->isEmpty();
                $hideContributions = (string) ($employee?->employee_number ?? '') === '0014939';
                $lastName = trim((string) ($employee?->last_name ?? ''));
                $firstName = trim((string) ($employee?->first_name ?? ''));
                $middleName = trim((string) ($employee?->middle_name ?? ''));
                $latestPayroll = $employeePayrolls->sortByDesc('start_date')->first();
                $hasCompleteDocuments = $latestPayroll?->details?->is_complete_documents
                    ?? $employee?->payroll_list?->has_complete_documents
                    ?? false;
                $victoryStatus = $hasCompleteDocuments ? 'VICTORY' : 'NON-VICTORY';
                $taxTypeRaw = strtoupper(trim((string) ($employee?->tax_type ?? $payroll?->tax_status ?? '')));
                $homeBranchLocation = strtoupper((string) ($employee?->home_branch?->location ?? ''));
                $isNcrMinimumNonTaxable = str_contains($homeBranchLocation, 'NCR')
                    && $employeePayrolls->contains(function ($employeePayroll) {
                        return in_array(round((float) ($employeePayroll?->daily_rate ?? 0), 2), [695.00, 821.36], true);
                    });
                $nonTaxableLabel = str_contains($taxTypeRaw, 'NON') || $isNcrMinimumNonTaxable
                    ? 'NON TAXABLE'
                    : 'TAXABLE';
                $fullName = $lastName;

                if ($firstName !== '') {
                    $fullName = $fullName !== '' ? ($fullName . ', ' . $firstName) : $firstName;
                }

                if ($middleName !== '') {
                    $fullName = $fullName !== '' ? ($fullName . ' ' . $middleName) : $middleName;
                }
    
                $basicPay = $payrollsForDisplay->sum('salary')
                    - $payrollsForDisplay->sum('undertime_amount')
                    - $payrollsForDisplay->sum('tardiness_amount');
                $legalHolidayAmount = $payrollsForDisplay->sum('legal_holiday_amount')
                    + $payrollsForDisplay->sum('special_holiday_amount');
                $overtimeAmount = $payrollsForDisplay->sum('overtime_amount')
                    + $payrollsForDisplay->sum('legal_holiday_overtime_amount')
                    + $payrollsForDisplay->sum('special_holiday_overtime_amount')
                    + $payrollsForDisplay->sum('night_differential_overtime_amount')
                    + $payrollsForDisplay->sum('cancelled_off');
                $nightDiffAmount = $payrollsForDisplay->sum('night_differential_amount');
                $withholdingTax = $payrollsForDisplay->sum('withholding_tax');
                $employeeSss = $employeePayrolls->sum('employee_sss');
                $employerSssTotal = $employeePayrolls->sum('employer_sss');
                $employerSss = $employerSssTotal;
                $employerEc = 0;
                $sssBasis = null;

                if ($employeeSss > 0 || $employerSssTotal > 0) {
                    $sssBasis = SSS::where('employee_ss', round($employeeSss, 2))
                        ->whereRaw('ROUND(employer_ss + employer_cc, 2) = ?', [round($employerSssTotal, 2)])
                        ->first();
                }

                if ($sssBasis) {
                    $employerSss = (float) $sssBasis->employer_ss;
                    $employerEc = (float) $sssBasis->employer_cc;
                }
                $employeePhilhealth = $employeePayrolls->sum('employee_philhealth');
                $employerPhilhealth = $employeePayrolls->sum('employer_philhealth');
                $employeePagibig = $employeePayrolls->sum('employee_pagibig');
                $employerPagibig = $employeePayrolls->sum('employer_pagibig');
                $totalDeductions = $employeePayrolls->sum('total_deductions');
                $thirteenthMonthPay = 0;
                $remarks = '';

                if (!empty($employee?->separation_date)) {
                    $separationDate = Carbon::parse($employee->separation_date);
                    $yearHolded = (int) $separationDate->year;
                    $dec11 = Carbon::create($yearHolded, 12, 11)->startOfDay();
                    if ($separationDate->greaterThanOrEqualTo($dec11)) {
                        $yearHolded += 1;
                    }
                    $thirteenthMonthPeriodStart = Carbon::create($yearHolded - 1, 12, 11)->format("Y-m-d");
                    $thirteenthMonthPeriodEnd = Carbon::create($yearHolded, 12, 10)->format("Y-m-d");

                    $annualSalary = 0;
                    $thirteenthPayrolls = Payroll::where("employee_id", $employee->id)
                        ->where("status", "final")
                        ->whereBetween("end_date", [$thirteenthMonthPeriodStart, $thirteenthMonthPeriodEnd])
                        ->get();

                    foreach ($thirteenthPayrolls as $thirteenthPayroll) {
                        $salary = ($thirteenthPayroll->salary ?? 0)
                            - (($thirteenthPayroll->undertime_amount ?? 0) + ($thirteenthPayroll->tardiness_amount ?? 0));
                        $annualSalary += $salary;
                    }

                    $thirteenthMonthPay = $annualSalary / 12;
                    $remarks = 'Resign Date : ' . $separationDate->format('F d Y');
                }

                if ($isContributionOnly) {
                    $basicPay = 0;
                    $legalHolidayAmount = 0;
                    $overtimeAmount = 0;
                    $nightDiffAmount = 0;
                    $withholdingTax = 0;
                    $thirteenthMonthPay = 0;
                }

                if ($hideContributions) {
                    $employeeSss = 0;
                    $employerSss = 0;
                    $employerEc = 0;
                    $employeePhilhealth = 0;
                    $employerPhilhealth = 0;
                    $employeePagibig = 0;
                    $employerPagibig = 0;
                }

                $sheet->setCellValueExplicit('A' . $row, (string) ($employee?->employee_number ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B' . $row, (string) $region, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C' . $row, (string) $fullName, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D' . $row, (string) $branchCode, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E' . $row, (string) ($payroll?->tax_status ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValue('F' . $row, (float) ($payroll?->daily_rate ?? 0));
                $sheet->setCellValueExplicit('G' . $row, (string) $victoryStatus, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('H' . $row, (string) $nonTaxableLabel, DataType::TYPE_STRING);
                $sheet->setCellValue('I' . $row, (float) $basicPay);
                $sheet->setCellValue('J' . $row, (float) $legalHolidayAmount);
                $sheet->setCellValue('L' . $row, (float) $overtimeAmount);
                $sheet->setCellValue('M' . $row, (float) $nightDiffAmount);
                $sheet->setCellValue('O' . $row, (float) $withholdingTax);
                $sheet->setCellValue('P' . $row, (float) $employeeSss);
                $sheet->setCellValue('Q' . $row, (float) $employerSss);
                $sheet->setCellValue('R' . $row, (float) $employerEc);
                $sheet->setCellValue('T' . $row, (float) $employeePhilhealth);
                $sheet->setCellValue('U' . $row, (float) $employerPhilhealth);
                $sheet->setCellValue('W' . $row, (float) $employeePagibig);
                $sheet->setCellValue('X' . $row, (float) $employerPagibig);
                $sheet->setCellValue('AA' . $row, (float) $thirteenthMonthPay);
                $sheet->setCellValueExplicit('AB' . $row, (string) $remarks, DataType::TYPE_STRING);

                $row++;
            }

            $lastDataRow = $row - 1;
            $lastSummaryRow = $summaryBranches->count() > 0
                ? ($startRow + $summaryBranches->count() - 1)
                : ($startRow - 1);
            $keepThroughRow = max($lastDataRow, $lastSummaryRow);
            $firstUnusedRow = $keepThroughRow + 1;
            $lastUnusedRow = $templateTotalsRow - 1;

            if ($firstUnusedRow <= $lastUnusedRow) {
                $rowsToDelete = $lastUnusedRow - $firstUnusedRow + 1;
                $sheet->removeRow($firstUnusedRow, $rowsToDelete);
                $maxRow -= $rowsToDelete;
                $templateTotalsRow -= $rowsToDelete;
            }

            $summaryRow = $startRow;
            foreach ($summaryBranches as $branchName) {
                if ($summaryRow > $maxRow) {
                    break;
                }
                $sheet->setCellValueExplicit('AF' . $summaryRow, (string) $branchName, DataType::TYPE_STRING);
                $sheet->setCellValue("AG{$summaryRow}", "=SUMIF(D:D,AF{$summaryRow},S:S)");
                $sheet->setCellValue("AH{$summaryRow}", "=SUMIF(D:D,AF{$summaryRow},U:U)");
                $sheet->setCellValue("AI{$summaryRow}", "=SUMIF(D:D,AF{$summaryRow},X:X)");
                $sheet->setCellValue("AJ{$summaryRow}", "=SUM(AG{$summaryRow}:AI{$summaryRow})");
                if ($summaryRowStyleSource) {
                    $sheet->duplicateStyle($summaryRowStyleSource, "AF{$summaryRow}:AJ{$summaryRow}");
                }
                $sheet->getStyle("AF{$summaryRow}:AJ{$summaryRow}")
                    ->getFont()
                    ->setBold(false);
                $summaryRow++;
            }

            $lastSummaryRow = $summaryRow - 1;
            $summaryTotalRow = $lastSummaryRow + 1;
            if ($summaryTotalRow > $maxRow) {
                $sheet->insertNewRowBefore($summaryTotalRow, 1);
                $maxRow++;
                if ($summaryTotalRow <= $templateTotalsRow) {
                    $templateTotalsRow++;
                }
            }

            if ($summaryTotalRow >= $startRow) {
                $sheet->setCellValueExplicit('AF' . $summaryTotalRow, 'TOTAL', DataType::TYPE_STRING);
                $sheet->setCellValue("AG{$summaryTotalRow}", "=SUM(AG{$startRow}:AG{$lastSummaryRow})");
                $sheet->setCellValue("AH{$summaryTotalRow}", "=SUM(AH{$startRow}:AH{$lastSummaryRow})");
                $sheet->setCellValue("AI{$summaryTotalRow}", "=SUM(AI{$startRow}:AI{$lastSummaryRow})");
                $sheet->setCellValue("AJ{$summaryTotalRow}", "=SUM(AG{$summaryTotalRow}:AI{$summaryTotalRow})");
                if ($summaryTotalStyleSource) {
                    $sheet->duplicateStyle($summaryTotalStyleSource, "AF{$summaryTotalRow}:AJ{$summaryTotalRow}");
                } else {
                    $sheet->getStyle("AF{$summaryTotalRow}:AJ{$summaryTotalRow}")
                        ->getFont()
                        ->setBold(true);
                }
                $sheet->getStyle("AF{$summaryTotalRow}:AJ{$summaryTotalRow}")
                    ->getFont()
                    ->setBold(true);
            }

            $totalsRow = $templateTotalsRow;
            if ($totalsRow <= $keepThroughRow) {
                $totalsRow = $keepThroughRow + 1;
                if ($totalsRow > $sheet->getHighestRow()) {
                    $sheet->insertNewRowBefore($totalsRow, 1);
                }
            }

            $lastRowExpr = 'LOOKUP(2,1/(A:A<>""),ROW(A:A))';
            $totalColumns = [
                'I', 'J', 'K', 'L', 'M', 'N', 'O',
                'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W',
                'X', 'Y', 'Z', 'AA',
            ];

            for ($r = $startRow; $r <= $totalsRow; $r++) {
                if ($r < $totalsRow) {
                    $sheet->setCellValue("N{$r}", "=SUM(K{$r}+L{$r}+M{$r})");
                }
            }

            foreach ($totalColumns as $col) {
                $sheet->setCellValue("{$col}{$totalsRow}", "=SUM({$col}{$startRow}:INDEX({$col}:{$col},{$lastRowExpr}))");
            }

            $filename = 'STATUTORY-PAYROLL-' . $validated['year'] . '-' . str_pad((string) $validated['month'], 2, '0', STR_PAD_LEFT) . '.xlsx';

            return response()->streamDownload(function () use ($spreadsheet) {
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Throwable $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

}
