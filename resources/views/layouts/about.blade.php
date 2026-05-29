<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

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

<style>
.about-main {
  width: min(1200px, calc(100vw - 48px));
  margin: 0 auto;
  padding: 72px 0;
}
.content {
  display: flow-root;
  min-width: 0;
}
.content > * {
  max-width: 100%;
  min-width: 0;
}
.content > * + * {
  margin-top: 40px;
}
.content > .story,
.content > .about-content {
  margin-top: 16px;
}
.content > aside {
  float: right;
  width: min(380px, 36%);
  margin: 0 0 28px 40px;
  padding: 24px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface);
  box-shadow: 0 18px 50px rgba(15, 23, 42, .08);
}
#contact {
  clear: both;
}
@media (max-width: 860px) {
  .about-main { padding: 48px 0; }
  .content > aside {
    float: none;
    width: auto;
    margin: 28px 0 0;
  }
}
</style>
