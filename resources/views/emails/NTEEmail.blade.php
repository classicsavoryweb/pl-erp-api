@php
    use Carbon\Carbon;
    $dueDate = Carbon::now()->addDays(5)->format('F j, Y');
@endphp

<p>
Good day Ma’am/Sir!
<br/>
<br/>
Attached file is a copy of your AO/DA/Short Memo. Please make sure to read and understand your memo and signed it as your acknowledgement.<br/>
The said document must be sent on or before {{ $dueDate }} to HR Employee Relations. Please follow the given deadline.<br/>
You can send the document/s in our email (sfiemployeerelations@classicsavory.com) or in our viber number (09175945340).
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