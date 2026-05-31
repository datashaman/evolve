<?php

use App\Models\BlogPost;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.blog')] class extends Component {
    public array $article = [];

    public function mount(string $post): void
    {
        $blogPost = BlogPost::query()
            ->published()
            ->slug($post)
            ->first();

        abort_unless($blogPost, 404);

        $paragraphs = $this->paragraphs($blogPost->summary);
        $callout = count($paragraphs) > 2 ? array_pop($paragraphs) : $blogPost->summary;

        $this->article = [
            'title' => $blogPost->title,
            'date' => $blogPost->created_at?->format('M j, Y') ?? 'Field note',
            'read_time' => $this->readTime($blogPost->summary),
            'summary' => Str::limit($paragraphs[0] ?? $blogPost->summary, 180),
            'body' => $paragraphs,
            'callout' => $callout,
        ];
    }

    private function paragraphs(string $body): array
    {
        return array_values(array_filter(
            preg_split('/\R{2,}/', trim($body)) ?: [],
            fn (string $paragraph): bool => trim($paragraph) !== '',
        ));
    }

    private function readTime(string $body): string
    {
        $minutes = max(1, (int) ceil(str_word_count(strip_tags($body)) / 200));

        return $minutes.' min read';
    }
};
?>

<livewire:blog.article :article="$article" />
