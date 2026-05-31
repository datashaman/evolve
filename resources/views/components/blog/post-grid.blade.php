<?php

use Livewire\Component;

new class extends Component {
    public array $posts = [];
};
?>

<section class="blog-grid" aria-label="Latest articles">
    @foreach ($posts as $post)
        <livewire:blog.post-card :post="$post" :key="$post['slug']" />
    @endforeach
</section>
