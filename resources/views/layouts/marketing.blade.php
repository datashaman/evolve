<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

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

<style>
.marketing-main {
  display: grid;
  gap: 64px;
  padding-bottom: 72px;
}
.slot-note {
  width: min(1200px, calc(100vw - 48px));
  margin: 0 auto;
  padding: 32px;
  border: 1px dashed var(--border);
  border-radius: var(--radius);
  background: var(--surface);
  color: var(--muted);
}
</style>
