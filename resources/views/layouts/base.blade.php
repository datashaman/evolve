<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div class="site-shell">
    <livewire:site-header>
        <livewire:slot name="brand">Northstar Studio</livewire:slot>
        <livewire:slot name="action">{!! $slots->has('action') ? $slots->get('action')->toHtml() : 'Plan the site' !!}</livewire:slot>
        <a href="/about">About</a>
        <a href="#contact">Contact</a>
    </livewire:site-header>

    {!! $slot !!}

    <footer>
        {!! $slots->has('footer') ? $slots->get('footer')->toHtml() : 'Built with the component workbench.' !!}
    </footer>
</div>

<style>
.site-shell {
  min-height: 100vh;
  background: var(--surface-soft);
  color: var(--text);
  font-family: var(--font-sans);
}
footer {
  width: min(1200px, calc(100vw - 48px));
  margin: 0 auto;
  padding: 36px 0 48px;
  color: var(--muted);
}
</style>
