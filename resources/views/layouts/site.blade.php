<!DOCTYPE html>
@php(config(['livewire.inject_assets' => false]))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name', 'Evolve') }}</title>
    <link rel="stylesheet" href="{{ route('tokens') }}">
    @livewireStyles
    <link rel="stylesheet" href="{{ route('evolve.styles') }}?v={{ filemtime(resource_path('evolve/manifest.json')) }}">
</head>
<body>
    {{ $slot }}
</body>
</html>
