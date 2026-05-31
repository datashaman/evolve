<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        @vite(['resources/css/blog.css'])
        <title>{{ $title ?? 'Field Notes' }}</title>
    </head>
    <body class="blog-shell">
        <header class="blog-header">
            <div class="blog-container blog-header-inner">
                <a class="blog-brand" href="{{ route('home') }}" wire:navigate>Field Notes</a>
                <nav class="blog-nav" aria-label="Primary navigation">
                    <a href="{{ route('home') }}" wire:navigate>Articles</a>
                    <a href="{{ route('home') }}" wire:navigate>Home</a>
                </nav>
            </div>
        </header>

        <main class="blog-container blog-main">
            {{ $slot }}
        </main>

        @fluxScripts
    </body>
</html>
