<x-mail::message>
# Confirm your order

Thank you for ordering from {{ config('app.name') }}. Here is what you asked for:

@foreach ($descriptions as $description)
- {{ $description }}
@endforeach

Your order is not placed until you confirm it.

<x-mail::button :url="$confirmUrl">
Confirm my order
</x-mail::button>

This link is valid for 48 hours. If you did not place this order, ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
