@php
    use Carbon\Carbon;
@endphp

@extends('template.template')
@section('body')
<div class="container-fluid">
    <div class="wrapper">
        <table class="width-full bg-light" style="border-collapse: collapse;" border="2">
            <tbody>
                <tr>
                    <td class="bg-smoke-grey items-left semibold" style="padding: 8px;" colspan="3">
                        <span class="text-lg text-red">
                            {{ $company }}
                        </span>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 6px;" colspan="3">
                        <span class="semibold" style="padding-right: 7px;"> Pay Period </span>
                        {{ Carbon::parse($payroll->start_date)->format('F j, Y') }} to {{ Carbon::parse($payroll->end_date)->format('F j, Y') }}
                    </td>
                </tr>

                <tr>
                    <td style="padding: 6px;" colspan="3">
                        <span class="semibold" style="padding-right: 7px;"> Employee Name </span>
                        {{ $employee->full_name }}
                    </td>
                </tr>

                <tr>
                    <td style="padding: 6px;" colspan="3">
                        <span class="semibold" style="padding-right: 7px;"> Department / Branch </span>
                        {{ $employee->current_branch->name }}
                    </td>
                </tr>
                
                <tr>
                    <td class="bg-smoke-grey items-left semibold" style="padding: 6px;" colspan="3">
                        Working Days
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; text-align: left; vertical-align: top;" colspan="2">
                        <table width="100%" style="margin-top:5px; font-size:12px;">
                            <tr>
                                <td width="50%">Regular Days</td>
                                <td width="50%">{{ number_format($timekeeping->regular_days, 2) }}</td>
                            </tr>
                            <tr>
                                <td width="50%">Special Leave</td>
                                <td width="50%">{{ number_format($timekeeping->special_leave, 2) }}</td>
                                {{-- <td width="33%">{{ number_format($payroll->salary, 2) }}</td> --}}
                            </tr>
                            
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px;" colspan="3">
                        <span class="semibold" style="padding-right: 7px;"> Total Working Days</span>
                        {{ number_format($payroll->salary, 2) }}
                    </td>
                </tr>
                <tr>
                    <td class="bg-smoke-grey items-left semibold" style="padding: 6px;" colspan="3">
                        Working Hours Deductions
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; text-align: left; vertical-align: top;" colspan="2">
                        <table width="100%" style="margin-top:5px; font-size:12px;">
                            <tr>
                                <td>Tardiness</td>
                                <td>{{ number_format($timekeeping->tardiness, 2) }}</td>
                                <td>{{ number_format($payroll->tardiness_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Undertime</td>
                                <td>{{ number_format($timekeeping->undertime, 2) }}</td>
                                <td>{{ number_format($payroll->undertime_amount, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px;" colspan="3">
                        <span class="semibold" style="padding-right: 7px;"> Total Working Hours Deductions </span>
                        {{ number_format($payroll->working_hours_deduction_total, 2) }}
                    </td>
                </tr>

                <tr>
                    <td class="bg-smoke-grey items-left semibold" style="padding: 6px;" colspan="3">
                        Additional Working Hours
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; text-align: left; vertical-align: top;" colspan="2">
                        <table width="100%" style="margin-top:5px; font-size:12px;">
                            <tr>
                                <td width="33%">Regular Overtime</td>
                                <td width="33%">{{ number_format($timekeeping->regular_overtime, 2) }}</td>
                                <td width="33%">{{ number_format($payroll->overtime_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Special Holiday</td>
                                <td>{{ number_format($timekeeping->special_holiday, 2) }}</td>
                                <td>{{ number_format($payroll->special_holiday_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Legal Holiday</td>
                                <td>{{ number_format($timekeeping->legal_holiday, 2) }}</td>
                                <td>{{ number_format($payroll->legal_holiday_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Cancelled Off</td>
                                <td>{{ number_format($timekeeping->cancelled_off, 2) }}</td>
                                <td>{{ number_format($payroll->cancelled_off, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Cancelled Off OT</td>
                                <td>{{ number_format($timekeeping->cancelled_off_overtime, 2) }}</td>
                                <td>{{ number_format($payroll->cancelled_off_overtime_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Legal Holiday OT</td>
                                <td>{{ number_format($timekeeping->legal_holiday_overtime, 2) }}</td>
                                <td>{{ number_format($payroll->legal_holiday_overtime_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Special Holiday OT</td>
                                <td>{{ number_format($timekeeping->special_holiday_overtime, 2) }}</td>
                                <td>{{ number_format($payroll->special_holiday_overtime_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Night Diff</td>
                                <td>{{ number_format($timekeeping->night_differential, 2) }}</td>
                                <td>{{ number_format($payroll->night_differential_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Night Diff OT</td>
                                <td>{{ number_format($timekeeping->night_differential_overtime, 2) }}</td>
                                <td>{{ number_format($payroll->night_differential_overtime_amount, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px;" colspan="3">
                        <span class="semibold" style="padding-right: 7px;"> Total Additional Working Hours </span>
                        {{ number_format($payroll->additional_working_hours_total, 2) }}
                    </td>
                </tr>
                
                <tr>
                    <td class="bg-smoke-grey items-left semibold" style="padding: 6px;" colspan="3">
                        Statutory Deductions
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; text-align: left; vertical-align: top;" colspan="2">
                        <table width="100%" style="margin-top:5px; font-size:12px;">
                            <tr>
                                <td width="50%">SSS</td>
                                <td width="50%">{{ number_format($payroll->employee_sss, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Philhealth</td>
                                <td>{{ number_format($payroll->employee_philhealth, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Pag-ibig</td>
                                <td>{{ number_format($payroll->employee_pagibig, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Withholding Tax</td>
                                <td>{{ number_format($payroll->withholding_tax, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td class="bg-smoke-grey items-left semibold" style="padding: 6px;" colspan="3">
                        Deductions
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; text-align: left; vertical-align: top;" colspan="2">
                        <table width="100%" style="margin-top:5px; font-size:12px;">
                            @foreach ($payroll->deductions as $deduction)
                            <tr>
                                <td width="50%">{{ $deduction["payslip_description"] ?? $deduction["type"]  }}</td>
                                <td width="50%">{{ number_format($deduction["deducted_amount"], 2) }}</td>
                            </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px;" colspan="3">
                        <span class="semibold" style="padding-right: 7px;"> Total Deductions </span>
                        {{ number_format($payroll->total_deductions, 2) }}
                    </td>
                </tr>

                <tr>
                    <td class="bg-smoke-grey items-left semibold" style="padding: 6px;" colspan="3">
                        Adjustments
                    </td>
                </tr>
                <tr>
                    <td style="padding: 2px; text-align: left; vertical-align: top;" colspan="2">
                        <table width="100%" style="margin-top:5px; font-size:12px;">
                            <tr>
                                <td width="50%">Taxable Adjustment</td>
                                <td width="50%">{{ number_format($payroll->taxable_adjustment, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Non-Taxable Adjustment</td>
                                <td>{{ number_format($payroll->non_taxable_adjustment, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr class="bg-smoke-grey">
                    <td style="padding: 6px; text-align: left;" colspan="1">
                        <span class="semibold" style="padding-right: 10px;">Gross Pay</span>
                        {{ number_format($payroll->gross_pay, 2) }}
                    </td>
                    <td style="padding: 6px; text-align: left;" colspan="2">
                        <span class="semibold" style="padding-right: 10px;">Net Pay</span>
                        {{ number_format($payroll->net_pay, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection