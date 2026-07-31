@php
    use Illuminate\Support\Str;
    use Carbon\Carbon;
@endphp

@extends('template.template')
@section('body')
<div class="container-fluid">
    <div class="wrapper">
        <p>A deposit has been credited to your account ending in <b>{{ Str::substr($transaction->employee->latest_bank_details->bank_number, -4) }}</b>.</p>
        <br/>

        <table class="width-full bg-light" style="border-collapse: collapse;" border="2">
            <tbody>
                <tr>
                    <td class="bg-smoke-grey items-left semibold" style="padding: 8px;" colspan="2">
                        <span class="text-lg text-red">
                            {{ $company }}
                        </span>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 6px; font-size:14px;" colspan="3">
                        <span class="semibold" style="padding-right: 7px;">Transaction Details</span>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 6px; font-size:13px;" colspan="1">
                        <span class="semibold" style="padding-right: 7px;">Date Credited</span>
                    </td>

                    <td style="padding: 6px; font-size:13px;" colspan="2">
                        <span>{{ Carbon::parse($transaction->crediting_date ?? $transaction->date_created)->format('F j, Y') }}</span>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 6px; font-size:13px;" colspan="1">
                        <span class="semibold" style="padding-right: 7px;">Transaction Type</span>
                    </td>

                    <td style="padding: 6px; font-size:13px;" colspan="2">
                        <span>{{ $transaction->transaction_type->description }}</span>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 6px; font-size:13px;" colspan="1">
                        <span class="semibold" style="padding-right: 7px;">Amount</span>
                    </td>

                    <td style="padding: 6px; font-size:13px;" colspan="2">
                        <span>{{ number_format($transaction->amount, 2) }}</span>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 6px; font-size:13px;" colspan="1">
                        <span class="semibold" style="padding-right: 7px;">Remarks</span>
                    </td>
                    <td style="padding: 6px; font-size:13px;" colspan="2">
                        <span>{{ $transaction->remarks ?? "-" }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection