<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.site')] #[Title('About Northstar Studio')] class extends Component {
    //
};
?>

<livewire:layouts::about>
    <livewire:slot name="hero">
        <section class="intro">
            <p class="eyebrow">About the system</p>
            <h1>Small pieces, clear ownership, real framework runtime.</h1>
            <p class="lede">Evolve now edits Livewire single-file components directly, so the workbench and production app share the same component model.</p>
        </section>
    </livewire:slot>

    <livewire:slot name="main">
        <section class="story">
            <h2>The workbench is no longer a parallel CMS.</h2>
            <p>Pages, layouts, and components are Livewire SFCs with anonymous classes, Blade markup, and scoped style. The editor is an affordance over framework files, not a separate renderer.</p>
        </section>
        <div class="principles">
            <livewire:feature-card icon="01" title="Native files">
                <livewire:slot name="body">Source lives under resources/views, where Laravel and Livewire already expect it.</livewire:slot>
            </livewire:feature-card>
            <livewire:feature-card icon="02" title="Real previews">
                <livewire:slot name="body">The preview iframe loads Laravel routes and Livewire components, not generated srcdoc.</livewire:slot>
            </livewire:feature-card>
        </div>
    </livewire:slot>

    <livewire:slot name="aside">
        <strong>Studio profile</strong>
        <p>Server-rendered pages, reusable SFCs, authenticated workbench.</p>
    </livewire:slot>

    <livewire:slot name="cta">
        <livewire:cta-panel title="Make this your starting point." action="about@northstar.test" href="mailto:about@northstar.test">
            <livewire:slot name="body">Build on a Laravel starter kit instead of maintaining auth and app shell by hand.</livewire:slot>
        </livewire:cta-panel>
    </livewire:slot>

    <livewire:slot name="footer">Northstar Studio. About this Livewire SFC scaffold.</livewire:slot>
</livewire:layouts::about>

<style>
.intro h1 {
  margin: 0;
  color: var(--ink);
  font-size: clamp(2.6rem, 7vw, 5.6rem);
  line-height: 1;
}
.eyebrow {
  margin: 0 0 14px;
  color: var(--accent);
  font-size: .78rem;
  font-weight: 900;
  letter-spacing: .12em;
  text-transform: uppercase;
}
.lede,
.story p,
aside p {
  color: var(--muted);
  line-height: 1.75;
}
.story h2 {
  margin: 0 0 12px;
  color: var(--ink);
  font-size: clamp(1.8rem, 4vw, 3rem);
}
.principles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
}
@media (max-width: 720px) {
  .principles { grid-template-columns: 1fr; }
}
</style>
