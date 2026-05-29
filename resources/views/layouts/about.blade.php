<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<livewire:layouts::base>
    <livewire:slot name="action">Start a conversation</livewire:slot>

    <main class="about-main">
        <article class="content">
            {!! $slots->has('hero') ? $slots->get('hero')->toHtml() : '<h1>An editorial shell for trust-building pages.</h1>' !!}
            {!! $slots->has('main') ? $slots->get('main')->toHtml() : '<p>Page narrative content appears here.</p>' !!}
            {!! $slots->has('cta') ? $slots->get('cta')->toHtml() : '' !!}
        </article>

        <aside>
            {!! $slots->has('aside') ? $slots->get('aside')->toHtml() : 'Preview side-panel content.' !!}
        </aside>
    </main>

    <livewire:slot name="footer">{!! $slots->has('footer') ? $slots->get('footer')->toHtml() : 'Built with the component workbench.' !!}</livewire:slot>
</livewire:layouts::base>

<style>
.about-main {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(240px, 320px);
  gap: clamp(32px, 6vw, 80px);
  width: min(1200px, calc(100vw - 48px));
  margin: 0 auto;
  padding: 72px 0;
}
.content {
  display: grid;
  gap: 32px;
}
aside {
  align-self: start;
  position: sticky;
  top: 24px;
  padding: 24px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface);
  box-shadow: 0 18px 50px rgba(15, 23, 42, .08);
}
@media (max-width: 860px) {
  .about-main { grid-template-columns: 1fr; }
  aside { position: static; }
}
</style>
