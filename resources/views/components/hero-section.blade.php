<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<section>
    <div class="copy">
        <p class="eyebrow">{!! $slots->has('eyebrow') ? $slots->get('eyebrow')->toHtml() : 'Strategy and delivery' !!}</p>
        <h1>{!! $slots->has('title') ? $slots->get('title')->toHtml() : 'Launch a sharper service business online.' !!}</h1>
        <p class="body">{!! $slots->has('body') ? $slots->get('body')->toHtml() : 'A focused site system for consultants, studios, and operators who need a credible web presence without a sprawling build.' !!}</p>
        <div class="actions">
            <a class="primary" href="#contact">{!! $slots->has('primary') ? $slots->get('primary')->toHtml() : 'Start a project' !!}</a>
            <a class="secondary" href="#services">{!! $slots->has('secondary') ? $slots->get('secondary')->toHtml() : 'Explore services' !!}</a>
        </div>
    </div>
    <aside>
        {!! $slots->has('aside') ? $slots->get('aside')->toHtml() : '<strong>Site system</strong><p>Reusable sections, shared tokens, and server-rendered pages are ready to customize.</p>' !!}
    </aside>
</section>

<style>
& {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(280px, .9fr);
  gap: clamp(32px, 7vw, 96px);
  align-items: center;
  width: min(1200px, calc(100vw - 48px));
  margin: 0 auto;
  padding: 88px 0;
  font-family: var(--font-sans);
}
.eyebrow {
  margin: 0 0 16px;
  color: var(--accent);
  font-size: .8rem;
  font-weight: 900;
  letter-spacing: .12em;
  text-transform: uppercase;
}
h1 {
  margin: 0;
  color: var(--ink);
  font-size: clamp(3rem, 8vw, 6.8rem);
  line-height: .95;
  letter-spacing: 0;
}
.body {
  margin: 26px 0 0;
  max-width: 58ch;
  color: var(--muted);
  font-size: 1.12rem;
  line-height: 1.75;
}
.actions {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
  margin-top: 30px;
}
a {
  padding: 13px 18px;
  border-radius: 8px;
  font-weight: 800;
  text-decoration: none;
}
.primary { background: var(--accent); color: #fff; }
.secondary { background: var(--surface-muted); color: var(--ink); }
aside {
  padding: 26px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface);
  color: var(--muted);
  box-shadow: 0 24px 70px rgba(15, 23, 42, .10);
}
aside strong { color: var(--ink); }
@media (max-width: 860px) {
  & { grid-template-columns: 1fr; padding: 56px 0; }
}
</style>
