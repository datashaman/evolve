<?php

use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('required|string|max:255')]
    public string $name = '';

    public function save(): void
    {
        $this->validate();

        $this->reset('name');
    }
};
?>

<form wire:submit="save">
  <label>
    <span>Name</span>
    <input type="text" wire:model="name">
  </label>

  @error('name') <p>{{ $message }}</p> @enderror

  <button type="submit">Submit</button>
</form>

<style>
& {
  display: grid;
  gap: 18px;
  max-width: 460px;
  padding: 32px;
  border: 1px solid #d4d4d8;
  border-radius: 8px;
  background: #ffffff;
}

label {
  display: grid;
  gap: 10px;
  color: #27272a;
  font-weight: 700;
}

input {
  width: 100%;
  padding: 13px 14px;
  border: 1px solid #a1a1aa;
  border-radius: 6px;
}

button {
  justify-self: start;
  padding: 12px 18px;
  border-radius: 6px;
  background: #4338ca;
  color: #ffffff;
  font-weight: 800;
}

p {
  margin: -6px 0 0;
  color: #b91c1c;
}
</style>
