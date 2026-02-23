@php
    use Carbon\Carbon;
@endphp

<table style="border-collapse: collapse; font-size: 13px; width: 100%;" border="1">
    <tbody>
        <tr>
            <th style="padding: 2px; text-align: left;" colspan="2">
                {{ $company }}
            </th>
        </tr>

        <tr>
            <td style="padding: 2px;" colspan="2">
                <strong style="padding-right: 10px;"> Pay Period </strong>
                {{ Carbon::parse($payroll->start_date)->format('F j, Y') }} to {{ Carbon::parse($payroll->end_date)->format('F j, Y') }}
            </td>
        </tr>

        <tr>
            <td style="padding: 2px;" colspan="2">
                <strong style="padding-right: 10px;"> Employee Name </strong>
                {{ $employee->full_name }}
            </td>
        </tr>

        <tr>
            <td style="padding: 2px;" colspan="2">
                <strong style="padding-right: 10px;"> Department / Branch </strong>
                {{ $employee->current_branch->name }}
            </td>
        </tr>

        <tr>
            <th style="padding: 2px; text-align: left; vertical-align: top;">
                Total Days

                <table width="100%" style="margin-top:5px; font-size:12px;">
                    <tr>
                        <td width="33%">Regular Days</td>
                        <td width="33%">{{ number_format($timekeeping->regular_days, 2) }}</td>
                        <td width="33%">{{ number_format($payroll->salary, 2) }}</td>
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
                </table>
            </th>

            <th style="padding: 2px; text-align: left; vertical-align: top;">
                Statutory Deductions

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
            </th>
        </tr>
        <tr>
            <th style="padding: 2px; text-align: left; vertical-align: top;">
                Total Overtime

                <table width="100%" style="margin-top:5px; font-size:12px;">
                    <tr>
                        <td width="33%">Regular Overtime</td>
                        <td width="33%">{{ number_format($timekeeping->regular_overtime, 2) }}</td>
                        <td width="33%">{{ number_format($payroll->overtime_amount, 2) }}</td>
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
            </th>

            <th style="padding: 2px; text-align: left; vertical-align: top;">
                Deductions
                <table width="100%" style="margin-top:5px; font-size:12px;">
                    @foreach ( json_decode($payroll->deductions) as $deduction)
                    <tr>
                        <td width="50%">{{ $deduction->payslip_description ?? $deduction->type  }}</td>
                        <td width="50%">{{ number_format($deduction->deducted_amount, 2) }}</td>
                    </tr>
                    @endforeach
                </table>
            </th>
        </tr>
        
        <tr>
            <th style="padding: 2px; text-align: left; vertical-align: top;">
                Adjustments
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
            </th>

            <th style="padding: 2px; text-align: left; font-size:12px;">
                <div style="display:flex; justify-content: space-between;">
                    <p style="padding-right: 10px;">Total Deductions</p>
                    <p>{{ number_format($payroll->total_deductions, 2) }}</p>
                </div>
            </th>
        </tr>

        <tr>
            <th style="padding: 2px; text-align: left;">
                <div style="display:flex; justify-content: space-between;">
                    <p style="padding-right: 10px;">GROSS PAY</p>
                    <p>{{ number_format($payroll->gross_pay, 2) }}</p>
                </div>
            </th>

            <th style="padding: 2px; text-align: left;">
                <div style="display:flex; justify-content: space-between;">
                    <p style="padding-right: 10px;">NET PAY</p>
                    <p>{{ number_format($payroll->net_pay, 2) }}</p>
                </div>
            </th>
        </tr>
    </tbody>
</table>