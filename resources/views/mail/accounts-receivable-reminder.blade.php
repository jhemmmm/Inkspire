<x-mail::message>
# Accounts Receivable Notice

{{ $lead }}

**Customer:** {{ $customerName }}<br>
**Job Order:** {{ $jobOrderNumber }}<br>
**Outstanding Balance:** ₱{{ number_format($outstandingBalance, 2) }}<br>
**Due Date:** {{ $dueDateFormatted }}<br>
**Days Past Due:** {{ $daysPastDue }}<br>
**Collection Status:** {{ $collectionStatusLabel }}

{{ $closing }}

{{ config('app.name') }} — automated accounts receivable notice. No reply is needed.
</x-mail::message>
