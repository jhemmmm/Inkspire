<x-mail::message>
Dear {{ $customerName }},

{{ $body }}

**Job Order:** {{ $number }} ({{ $description }})<br>
**Balance:** ₱{{ $balance }}
@if ($dueDate !== null)
<br>**Due date:** {{ $dueDate }}
@endif

Please pay at the shop counter by cash, bank transfer, GCash or Maya.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
