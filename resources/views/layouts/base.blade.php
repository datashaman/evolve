<!DOCTYPE html>
@php(config(['livewire.inject_assets' => false]))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name', 'Evolve') }}</title>
    @livewireStyles
    <link rel="stylesheet" href="{{ route('evolve.styles') }}?v={{ filemtime(resource_path('evolve/manifest.json')) }}">
</head>
<body>
    <div class="site-shell">
        <livewire:site-header>
            <livewire:slot name="brand">Northstar Studio</livewire:slot>
            <livewire:slot name="action">{!! $action ?? 'Plan the site' !!}</livewire:slot>
            <a href="/about">About</a>
            <a href="#contact">Contact</a>
        </livewire:site-header>

        {!! $slot !!}

        <footer>
            {!! $footer ?? 'Built with the component workbench.' !!}
        </footer>
    </div>
    @livewireScripts
</body>
</html>
