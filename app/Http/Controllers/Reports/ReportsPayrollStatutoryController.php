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

            $payrolls = Payroll::with(['employee', 'employee.current_branch', 'timekeeping_branch', 'employee.current_position'])
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
            $summaryRowStyleSource = $sheet->getStyle("AG{$startRow}:AK{$startRow}");
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
                $value = $sheet->getCell("AG{$r}")->getValue();
                if (is_string($value) && strtoupper(trim($value)) === 'TOTAL') {
                    $summaryTotalTemplateRow = $r;
                    $summaryTotalStyleSource = $sheet->getStyle("AG{$r}:AK{$r}");
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
                        $sheet->getStyle("A{$templateRow}:AK{$templateRow}"),
                        "A{$r}:AK{$r}"
                    );

                    $sheet->setCellValue("K{$r}", "=SUM(I{$r}+J{$r})");
                    $sheet->setCellValue("O{$r}", "=SUM(K{$r}+L{$r}+M{$r}+N{$r})");
                    $sheet->setCellValue("T{$r}", "=SUM(R{$r}+S{$r})");
                    $sheet->setCellValue("W{$r}", "=SUM(U{$r}+V{$r})");
                    $sheet->setCellValue("Z{$r}", "=SUM(X{$r}+Y{$r})");
                    $sheet->setCellValue("AA{$r}", "=SUM(Q{$r}+U{$r}+X{$r})");
                    $sheet->setCellValue("AH{$r}", "=SUMIF(D:D,AG:AG,T:T)");
                    $sheet->setCellValue("AI{$r}", "=SUMIF(D:D,AG:AG,V:V)");
                    $sheet->setCellValue("AJ{$r}", "=SUMIF(D:D,AG:AG,Y:Y)");
                    $sheet->setCellValue("AK{$r}", "=SUM(AH{$r}:AJ{$r})");
                }

                $maxRow = $newMaxRow;
                $maxDataRows = $maxRow - $startRow + 1;
            }

            $inputColumns = [
                'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
                'I', 'J', 'L', 'M', 'N', 'P',
                'Q', 'R', 'S', 'U', 'V', 'X', 'Y',
                'AB', 'AC',
            ];

            for ($row = $startRow; $row <= $maxRow; $row++) {
                foreach ($inputColumns as $col) {
                    $sheet->setCellValue($col . $row, null);
                }
                $sheet->setCellValue('AG' . $row, null);
            }

            $row = $startRow;
            foreach ($payrollGroups as $employeePayrolls) {
                if ($row > $maxRow) {
                    break;
                }

                $payroll = $employeePayrolls->first();
                $employee = $payroll?->employee;
                $branch = $employeePayrolls->pluck('timekeeping_branch')->filter()->first()
                    ?? $employee?->current_branch;
                $branchCode = $branch?->short_name ?? '';
                $region = $branch?->location ?? '';
                $lastName = trim((string) ($employee?->last_name ?? ''));
                $firstName = trim((string) ($employee?->first_name ?? ''));
                $middleName = trim((string) ($employee?->middle_name ?? ''));
                $victoryStatus = $employee?->hasCompleteStatutories() ? 'VICTORY' : 'NON-VICTORY';
                $taxTypeRaw = strtoupper(trim((string) ($employee?->tax_type ?? $payroll?->tax_status ?? '')));
                $nonTaxableLabel = str_contains($taxTypeRaw, 'NON') ? 'NON TAXABLE' : 'TAXABLE';
                $fullName = $lastName;

                if ($firstName !== '') {
                    $fullName = $fullName !== '' ? ($fullName . ', ' . $firstName) : $firstName;
                }

                if ($middleName !== '') {
                    $fullName = $fullName !== '' ? ($fullName . ' ' . $middleName) : $middleName;
                }
    
                $basicPay = $employeePayrolls->sum('salary');
                $additionalWorkingHours = $employeePayrolls->sum('additional_working_hours_total');
                $taxableAdjustment = $employeePayrolls->sum('taxable_adjustment');
                $overtimeAmount = $employeePayrolls->sum('overtime_amount');
                $nightDiffAmount = $employeePayrolls->sum('night_differential_amount')
                    + $employeePayrolls->sum('night_differential_overtime_amount');
                $withholdingTax = $employeePayrolls->sum('withholding_tax');
                $grossPayTotal = $employeePayrolls->sum('gross_pay');
                $sssBasis = SSS::where('salary_range_min', '<=', $grossPayTotal)
                    ->where('salary_range_max', '>=', $grossPayTotal)
                    ->first();
                $employeeSss = $sssBasis?->employee_ss ?? 0;
                $employerSss = $sssBasis?->employer_ss ?? 0;
                $employerEc = $sssBasis?->employer_cc ?? 0;
                $employeePhilhealth = $employeePayrolls->sum('employee_philhealth');
                $employerPhilhealth = $employeePayrolls->sum('employer_philhealth');
                $employeePagibig = $employeePayrolls->sum('employee_pagibig');
                $employerPagibig = $employeePayrolls->sum('employer_pagibig');
                $totalDeductions = $employeePayrolls->sum('total_deductions');
                $thirteenthMonthPay = 0;
                $remarks = (string) ($payroll?->remarks ?? '');

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
                    $remarks = 'Resigned Date : ' . $separationDate->format('F d Y');
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
                $sheet->setCellValue('J' . $row, (float) $additionalWorkingHours);
                $sheet->setCellValue('L' . $row, (float) $taxableAdjustment);
                $sheet->setCellValue('M' . $row, (float) $overtimeAmount);
                $sheet->setCellValue('N' . $row, (float) $nightDiffAmount);
                $sheet->setCellValue('P' . $row, (float) $withholdingTax);
                $sheet->setCellValue('Q' . $row, (float) $employeeSss);
                $sheet->setCellValue('R' . $row, (float) $employerSss);
                $sheet->setCellValue('S' . $row, (float) $employerEc);
                $sheet->setCellValue('U' . $row, (float) $employeePhilhealth);
                $sheet->setCellValue('V' . $row, (float) $employerPhilhealth);
                $sheet->setCellValue('X' . $row, (float) $employeePagibig);
                $sheet->setCellValue('Y' . $row, (float) $employerPagibig);
                $sheet->setCellValue('AB' . $row, (float) $thirteenthMonthPay);
                $sheet->setCellValueExplicit('AC' . $row, (string) $remarks, DataType::TYPE_STRING);

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
                $sheet->setCellValueExplicit('AG' . $summaryRow, (string) $branchName, DataType::TYPE_STRING);
                $sheet->setCellValue("AH{$summaryRow}", "=SUMIF(D:D,AG{$summaryRow},T:T)");
                $sheet->setCellValue("AI{$summaryRow}", "=SUMIF(D:D,AG{$summaryRow},V:V)");
                $sheet->setCellValue("AJ{$summaryRow}", "=SUMIF(D:D,AG{$summaryRow},Y:Y)");
                $sheet->setCellValue("AK{$summaryRow}", "=SUM(AH{$summaryRow}:AJ{$summaryRow})");
                if ($summaryRowStyleSource) {
                    $sheet->duplicateStyle($summaryRowStyleSource, "AG{$summaryRow}:AK{$summaryRow}");
                }
                $sheet->getStyle("AG{$summaryRow}:AK{$summaryRow}")
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
                $sheet->setCellValueExplicit('AG' . $summaryTotalRow, 'TOTAL', DataType::TYPE_STRING);
                $sheet->setCellValue("AH{$summaryTotalRow}", "=SUM(AH{$startRow}:AH{$lastSummaryRow})");
                $sheet->setCellValue("AI{$summaryTotalRow}", "=SUM(AI{$startRow}:AI{$lastSummaryRow})");
                $sheet->setCellValue("AJ{$summaryTotalRow}", "=SUM(AJ{$startRow}:AJ{$lastSummaryRow})");
                $sheet->setCellValue("AK{$summaryTotalRow}", "=SUM(AH{$summaryTotalRow}:AJ{$summaryTotalRow})");
                if ($summaryTotalStyleSource) {
                    $sheet->duplicateStyle($summaryTotalStyleSource, "AG{$summaryTotalRow}:AK{$summaryTotalRow}");
                } else {
                    $sheet->getStyle("AG{$summaryTotalRow}:AK{$summaryTotalRow}")
                        ->getFont()
                        ->setBold(true);
                }
                $sheet->getStyle("AG{$summaryTotalRow}:AK{$summaryTotalRow}")
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
                'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
                'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
                'Y', 'Z', 'AA', 'AB',
            ];

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
