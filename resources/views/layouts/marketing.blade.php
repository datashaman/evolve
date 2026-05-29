<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<livewire:layouts::base>
    <livewire:slot name="action">Plan the site</livewire:slot>

    <main class="marketing-main">
        @if ($slots->has('hero'))
            {!! $slots->get('hero')->toHtml() !!}
        @else
            <livewire:hero-section />
        @endif

        @if ($slots->has('main'))
            {!! $slots->get('main')->toHtml() !!}
        @else
            <section class="slot-note">Main content slot</section>
        @endif

        @if ($slots->has('cta'))
            {!! $slots->get('cta')->toHtml() !!}
        @else
            <livewire:cta-panel />
        @endif
    </main>

    <livewire:slot name="footer">{!! $slots->has('footer') ? $slots->get('footer')->toHtml() : 'Built with the component workbench.' !!}</livewire:slot>
</livewire:layouts::base>

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
