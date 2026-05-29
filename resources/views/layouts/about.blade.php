<x-layouts::base :title="$title ?? null">
    <x-slot:action>Start a conversation</x-slot:action>

    <main class="about-main">
        <article class="content">
            {!! $hero ?? '<h1>An editorial shell for trust-building pages.</h1>' !!}
            <aside>
                {!! $aside ?? 'Preview side-panel content.' !!}
            </aside>
            {!! trim((string) $slot) !== '' ? $slot : '<p>Page narrative content appears here.</p>' !!}
            {!! $cta ?? '' !!}
        </article>
    </main>

    <x-slot:footer>{!! $footer ?? 'Built with the component workbench.' !!}</x-slot:footer>
</x-layouts::base>
