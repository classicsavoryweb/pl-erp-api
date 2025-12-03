@php
    use Illuminate\Support\Str;
    use Carbon\Carbon;
@endphp

<b>{{ $company }}</b>
<br/>
<p>A deposit has been credited to your account ending in <b>{{ Str::substr($transaction->employee->latest_bank_details->bank_number, -4) }}</b>.</p>
<br/>

<table style="border-collapse: collapse;" border="1">
    <tbody>
        <tr>
            <th style="padding: 7px;" colspan="2">
                Transaction Details
            </th>
        </tr>
        <tr>
            <td style="padding: 7px;">
                Date Created
            </td>
    
            <td style="padding: 7px;">
                {{ Carbon::parse($transaction->date_created)->format('F j, Y') }}
            </td>
        </tr>
        <tr>
            <td style="padding: 7px;">
                Type
            </td>
    
            <td style="padding: 7px;">
                {{ $transaction->transaction_type->description }}
            </td>
        </tr>
        <tr>
            <td style="padding: 7px;">
                Amount
            </td>
    
            <td style="padding: 7px;">
                {{ number_format($transaction->amount, 2)}}
            </td>
        </tr>
        <tr>
            <td style="padding: 7px;">
                Remarks
            </td>

            <td style="padding: 7px;">
                {{ $transaction->remarks ?? "-" }}
            </td>
        </tr>
    </tbody>
</table>