<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
    #[Layout('layouts::marketing')]
    #[Title('Northstar Studio')]
class extends Component {
    //
};
?>

<x-slot:hero>
    <livewire:hero-section>
        <livewire:slot name="eyebrow">Website systems for service teams</livewire:slot>
        <livewire:slot name="title">A lean site scaffold that already behaves like a real project.</livewire:slot>
        <livewire:slot name="body">This seed gives you a credible home page, reusable sections, shared tokens, and server-rendered artifact composition you can reshape into your own offer.</livewire:slot>
        <livewire:slot name="primary">Start from this scaffold</livewire:slot>
        <livewire:slot name="secondary">Review the sections</livewire:slot>
        <livewire:slot name="aside">
            <strong>Livewire-native</strong>
            <p>Single-file components now power the page, preview, and workbench source.</p>
        </livewire:slot>
    </livewire:hero-section>
</x-slot:hero>

<div class="page-sections">
    <section id="services" class="services">
        <div>
            <p class="eyebrow">Reusable pieces</p>
            <h2>Everything is a Livewire SFC.</h2>
            <p>Components, layouts, and pages all keep PHP, Blade, and style together in real framework files.</p>
        </div>
        <div class="cards">
            <livewire:feature-card icon="01" title="Offer clarity">
                <livewire:slot name="body">Structure the page around the customer problem, service promise, and next step.</livewire:slot>
            </livewire:feature-card>
            <livewire:feature-card icon="02" title="Reusable sections">
                <livewire:slot name="body">Edit once, reuse across pages, and keep the site system small enough to understand.</livewire:slot>
            </livewire:feature-card>
            <livewire:feature-card icon="03" title="Token-driven theme">
                <livewire:slot name="body">Adjust color, spacing, radius, and typography without hunting through every artifact.</livewire:slot>
            </livewire:feature-card>
        </div>
    </section>

    <section class="proof">
        <p><strong>Server runtime.</strong> Livewire renders the actual SFCs the workbench edits.</p>
        <p><strong>Native composition.</strong> Pages use Livewire components and Blade slots directly.</p>
        <p><strong>Auth-ready.</strong> The workbench now lives inside the Laravel starter kit.</p>
    </section>
    </div>

<x-slot:cta>
    <livewire:cta-panel title="Make this your starting point." action="hello@northstar.test" href="mailto:hello@northstar.test">
        <livewire:slot name="body">Rename the brand, update the service cards, tune the tokens, and extend the library with real SFCs.</livewire:slot>
    </livewire:cta-panel>
</x-slot:cta>

<x-slot:footer>Northstar Studio. A seeded Livewire SFC workbench scaffold.</x-slot:footer>

<style global>
.services,
.proof {
  width: min(1200px, calc(100vw - 48px));
  margin: 0 auto;
}
.page-sections {
  display: grid;
  gap: 64px;
}
.services {
  display: grid;
  gap: 28px;
}
.services h2 {
  margin: 0;
  color: var(--ink);
  font-size: clamp(2rem, 5vw, 4rem);
}
.services p {
  color: var(--muted);
  line-height: 1.7;
}
.eyebrow {
  margin: 0 0 10px;
  color: var(--accent);
  font-size: .78rem;
  font-weight: 900;
  letter-spacing: .12em;
  text-transform: uppercase;
}
.cards {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
}
.proof {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
}
.proof p {
  margin: 0;
  padding: 22px;
  border-radius: var(--radius);
  background: var(--surface);
  color: var(--muted);
}
.proof strong { color: var(--ink); }
@media (max-width: 860px) {
  .cards,
  .proof { grid-template-columns: 1fr; }
}
</style>
