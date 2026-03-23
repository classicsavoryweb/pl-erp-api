@php
    use Carbon\Carbon;
    $dueDate = Carbon::now()->addDays(5)->format('F j, Y');
    $employeeRelationsEmail = env('COMPANY_NAME') == 'TTFC'
        ? 'ttfcemployeerelations@classicsavory.com'
        : 'sfiemployeerelations@classicsavory.com';
    $employeeRelationsViber = env('COMPANY_NAME') == 'TTFC'
        ? '+639178463769'
        : '09175945340';
@endphp

<p>
Good day Ma’am/Sir!
<br/>
<br/>
Attached file is a copy of your AO/DA/Short Memo. Please make sure to read and understand your memo and signed it as your acknowledgement.<br/>
The said document must be sent on or before {{ $dueDate }} to HR Employee Relations. Please follow the given deadline.<br/>
You can send the document/s in our email ({{ $employeeRelationsEmail }}) or in our viber number ({{ $employeeRelationsViber }}).
<br/>
<br/>
Should you have any questions, feel free to contact us.
<br/>
<br/>
Please reply to this message to confirm.
<br/>
<br/>
Thank you and stay safe.
</p>