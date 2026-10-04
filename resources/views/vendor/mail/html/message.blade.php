<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer: the same two lines as the homepage's. --}}
<x-slot:footer>
<x-mail::footer>
**Squarefoot Graphics & Ads**<br>
Job orders and tracking run on {{ config('app.name') }}.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
