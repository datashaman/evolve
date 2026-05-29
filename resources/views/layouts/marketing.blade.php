<x-layouts::base :title="$title ?? null">
    <x-slot:action>Plan the site</x-slot:action>

    <main class="marketing-main">
        @if (isset($hero))
            {!! $hero !!}
        @else
            <livewire:hero-section />
        @endif

        @if (trim((string) $slot) !== '')
            {!! $slot !!}
        @else
            <section class="slot-note">Main content slot</section>
        @endif

        @if (isset($cta))
            {!! $cta !!}
        @else
            <livewire:cta-panel />
        @endif
    </main>

    <x-slot:footer>{!! $footer ?? 'Built with the component workbench.' !!}</x-slot:footer>
</x-layouts::base>
