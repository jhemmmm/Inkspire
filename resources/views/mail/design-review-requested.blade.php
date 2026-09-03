<x-mail::message>
# Your design is ready for review

The design for **{{ $jobOrderDescription }}** is ready for your review.

<x-mail::button :url="$reviewUrl">
Review Your Design
</x-mail::button>

This link is valid for 7 days.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
