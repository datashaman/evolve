<?php

use Livewire\Component;

new class extends Component {
    public string $icon = '';

    public string $title = '';
};
?>

<article>
    <div class="icon">{{ $icon }}</div>
    <h3>{{ $title }}</h3>
    <p>{!! $slots->has('body') ? $slots->get('body')->toHtml() : 'Describe a focused customer outcome, not a generic capability.' !!}</p>
</article>

<style>
article {
  display: grid;
  gap: 16px;
  box-sizing: border-box;
  min-height: 100%;
  padding: 26px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface);
  box-shadow: 0 18px 50px rgba(15, 23, 42, .08);
  font-family: var(--font-sans);
}
.icon {
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  background: var(--surface-soft);
  color: var(--accent);
  font-weight: 800;
}
h3 {
  margin: 0;
  color: var(--ink);
  font-size: 1.1rem;
}
p {
  margin: 0;
  color: var(--muted);
  line-height: 1.65;
}
</style>
