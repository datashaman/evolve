<?php

use Livewire\Component;

new class extends Component {
    public string $title = 'Ready to turn this into your site?';

    public string $action = 'hello@example.com';

    public string $href = 'mailto:hello@example.com';
};
?>

<section id="contact">
    <div>
        <h2>{{ $title }}</h2>
        <p>{!! $slots->has('body') ? $slots->get('body')->toHtml() : 'Swap the copy, adjust tokens, and extend the components into a complete production-ready system.' !!}</p>
    </div>
    <a href="{{ $href }}">{{ $action }}</a>
</section>

<style>
section {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 24px;
  align-items: center;
  width: min(1200px, calc(100vw - 48px));
  box-sizing: border-box;
  margin: 0 auto;
  padding: 32px;
  border-radius: var(--radius);
  background: var(--ink);
  color: #fff;
  font-family: var(--font-sans);
}
h2 {
  margin: 0 0 10px;
  font-size: clamp(1.5rem, 3vw, 2.4rem);
}
p {
  margin: 0;
  max-width: 62ch;
  color: #dbe4ee;
  line-height: 1.65;
}
a {
  padding: 13px 18px;
  border-radius: 8px;
  background: #fff;
  color: var(--ink);
  font-weight: 800;
  text-decoration: none;
}
@media (max-width: 720px) {
  section { grid-template-columns: 1fr; }
}
</style>
