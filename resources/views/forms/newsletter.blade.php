<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new
    #[Layout('layouts::bare')]
    #[Title('Join the newsletter')]
class extends Component {
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    public bool $submitted = false;

    public function save(): void
    {
        $this->validate();

        $this->submitted = true;
        $this->reset('name', 'email');
    }
};
?>

<main>
  <section class="newsletter-card">
    <div class="eyebrow">Northstar notes</div>
    <h1>Sharper website thinking, once a month.</h1>
    <p class="intro">A short field note on positioning, page structure, and practical ways to turn a service business into a clearer site.</p>

    @if ($submitted)
      <div class="success" role="status">You are on the list. The next note will land soon.</div>
    @endif

    <form wire:submit="save">
      <label>
        <span>Name</span>
        <input type="text" wire:model="name" autocomplete="name" placeholder="Your name">
      </label>
      @error('name') <p class="error">{{ $message }}</p> @enderror

      <label>
        <span>Email</span>
        <input type="email" wire:model="email" autocomplete="email" placeholder="you@example.com">
      </label>
      @error('email') <p class="error">{{ $message }}</p> @enderror

      <button type="submit">Join the list</button>
    </form>

    <p class="fine-print">No drip sequence. No generic growth hacks. Unsubscribe anytime.</p>
  </section>
</main>

<style>
body {
  min-height: 100vh;
  background:
    radial-gradient(circle at top left, rgb(49 95 218 / 18%), transparent 34rem),
    linear-gradient(135deg, #f8fbff 0%, #eef4ff 52%, #e8eef8 100%);
}
</style>

<style>
& {
  min-height: 100svh;
  display: grid;
  place-items: center;
  padding: clamp(24px, 6vw, 72px);
}

.newsletter-card {
  width: min(100%, 560px);
  display: grid;
  gap: 18px;
  padding: clamp(28px, 5vw, 48px);
  border: 1px solid rgb(21 27 35 / 12%);
  border-radius: 8px;
  background: rgb(255 255 255 / 92%);
  box-shadow: 0 24px 70px rgb(17 24 39 / 14%);
}

.eyebrow {
  color: var(--accent);
  font-size: 13px;
  font-weight: 800;
  letter-spacing: .12em;
  text-transform: uppercase;
}

h1 {
  margin: 0;
  color: var(--ink);
  font-size: clamp(34px, 6vw, 56px);
  line-height: .98;
}

.intro,
.fine-print {
  margin: 0;
  color: var(--muted);
  font-size: 17px;
  line-height: 1.55;
}

form {
  display: grid;
  gap: 14px;
  margin-top: 8px;
}

label {
  display: grid;
  gap: 8px;
  color: var(--ink);
  font-weight: 800;
}

input {
  width: 100%;
  padding: 15px 16px;
  border: 1px solid #b9c5d1;
  border-radius: 8px;
  background: #fff;
  color: var(--ink);
  font: inherit;
}

input:focus {
  outline: 3px solid rgb(49 95 218 / 22%);
  border-color: var(--accent);
}

button {
  justify-self: start;
  min-height: 52px;
  padding: 0 22px;
  border: 0;
  border-radius: 8px;
  background: var(--ink);
  color: #fff;
  cursor: pointer;
  font: inherit;
  font-weight: 900;
}

button:hover {
  background: var(--accent-strong);
}

.success {
  padding: 12px 14px;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  background: #f0fdf4;
  color: #166534;
  font-weight: 700;
}

.error {
  margin: -6px 0 0;
  color: #b91c1c;
  font-weight: 700;
}
</style>
