<x-mail::message>
# We received your order

Thank you. Here {{ count($jobOrders) === 1 ? 'is the job order' : 'are the job orders' }} we have on file for you. Use the link to follow each one.

@foreach ($jobOrders as $jobOrder)
<x-mail::button :url="$jobOrder['url']">
Track {{ $jobOrder['number'] ?? 'your order' }}
</x-mail::button>

{{ $jobOrder['description'] }}

@endforeach
The same link is where you approve your design and pay once we have confirmed the price.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
