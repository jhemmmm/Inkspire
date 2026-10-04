<!DOCTYPE html>
{{-- Only the staff panel offers dark mode: the landing page, the brand-panel auth screens and the customer-facing public pages stay light. Keep in step with isLightOnlyPage() in useAppearance.ts. --}}
@php($lightOnly = $page['component'] === 'Welcome' || str_starts_with($page['component'], 'auth/') || str_starts_with($page['component'], 'errors/') || str_starts_with($page['component'], 'public/'))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => !$lightOnly && ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">

    {{-- Inline script to detect system dark mode preference and apply it immediately --}}
    <script>
        (function() {
            const appearance = '{{ $appearance ?? 'system' }}';

            if (appearance === 'system' && !@json($lightOnly)) {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (prefersDark) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();
    </script>

    {{-- Inline style to set the HTML background color based on our theme in app.css --}}
    <style>
        html {
            background-color: hsl(257.1 100% 98.6%);
        }

        html.dark {
            background-color: hsl(225 24% 7.5%);
        }
    </style>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
    <x-inertia::head>
        <title>{{ config('app.name', 'Laravel') }}</title>
    </x-inertia::head>
</head>

<body class="font-sans antialiased">
    <x-inertia::app />
</body>

</html>
