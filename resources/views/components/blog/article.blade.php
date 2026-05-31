<?php

use Livewire\Component;

new class extends Component {
    public array $article = [];
};
?>

<article class="blog-article">
    <a class="blog-back-link" href="{{ route('home') }}" wire:navigate>Back to articles</a>

    <div class="blog-article-meta">{{ $article['date'] }} &middot; {{ $article['read_time'] }}</div>
    <h1>{{ $article['title'] }}</h1>
    <p>{{ $article['summary'] }}</p>

    <div class="blog-prose">
        @foreach ($article['body'] as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach
    </div>

    <aside class="blog-callout">
        {{ $article['callout'] }}
    </aside>
</article>
