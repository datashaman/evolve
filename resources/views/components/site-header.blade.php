<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<header>
    <a class="brand" href="/">
        <span class="mark"></span>
        <strong>{!! $slots->has('brand') ? $slots->get('brand')->toHtml() : 'Northstar Studio' !!}</strong>
    </a>
    <nav>
        @if (trim((string) $slot) !== '')
            {!! $slot !!}
        @else
            <a href="/about">About</a>
            <a href="#contact">Contact</a>
        @endif
        <a class="action" href="#contact">{!! $slots->has('action') ? $slots->get('action')->toHtml() : 'Book a demo' !!}</a>
    </nav>
</header>

<style>
header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  width: min(1200px, calc(100vw - 48px));
  margin: 0 auto;
  padding: 22px 0;
  font-family: var(--font-sans);
}
.brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--ink);
  text-decoration: none;
}
.mark {
  width: 32px;
  height: 32px;
  border-radius: 9px;
  background: linear-gradient(135deg, var(--accent), var(--accent-strong));
}
nav {
  display: flex;
  align-items: center;
  gap: 20px;
}
nav :is(a) {
  color: var(--ink);
  font-weight: 700;
}
.action {
  padding: 11px 16px;
  border-radius: 8px;
  background: var(--ink);
  color: #fff;
  text-decoration: none;
}
@media (max-width: 720px) {
  header, nav { align-items: flex-start; flex-direction: column; }
}
</style>
