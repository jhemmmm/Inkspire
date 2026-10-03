<x-mail::message>
# Your order is ready for payment

The price for **{{ $number ?? 'your order' }}** ({{ $description }}) is confirmed.

Amount due: **₱{{ $amountDue }}**

<x-mail::button :url="$url">
Pay online
</x-mail::button>

You can pay by GCash or Maya from that link. You can also pay at the shop.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
