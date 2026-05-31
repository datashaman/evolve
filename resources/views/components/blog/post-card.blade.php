<?php

use Livewire\Component;

new class extends Component {
    public array $post = [];
};
?>

<a class="blog-card" href="{{ route('blog.show', ['post' => $post['slug']]) }}" wire:navigate>
    <div class="blog-card-meta">{{ $post['date'] }} &middot; {{ $post['read_time'] }}</div>
    <h2>{{ $post['title'] }}</h2>
    <p>{{ $post['summary'] }}</p>
    <span class="blog-card-link">Read article</span>
</a>
