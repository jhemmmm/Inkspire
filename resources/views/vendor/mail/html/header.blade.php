@props(['url'])
{{-- The shop's logo instead of the app name in text. It is loaded from
     APP_URL, so that has to be a public address for a real inbox to show it. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('logo.png') }}" class="logo" alt="{{ trim($slot) }}" width="152" height="56">
</a>
</td>
</tr>
