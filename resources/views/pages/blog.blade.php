<?php

use App\Models\BlogPost;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.blog')] class extends Component {
    public array $posts = [];

    public function mount(): void
    {
        $this->posts = BlogPost::query()
            ->published()
            ->ordered()
            ->get()
            ->map(fn (BlogPost $post): array => [
                'slug' => $post->slug,
                'title' => $post->title,
                'date' => $post->created_at?->format('M j, Y') ?? 'Field note',
                'read_time' => $this->readTime($post->summary),
                'summary' => Str::limit($this->firstParagraph($post->summary), 150),
            ])
            ->all();
    }

    private function firstParagraph(string $body): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($body));

        return $paragraphs[0] ?? $body;
    }

    private function readTime(string $body): string
    {
        $minutes = max(1, (int) ceil(str_word_count(strip_tags($body)) / 200));

        return $minutes.' min read';
    }
};
?>

<div>
    <livewire:blog.hero />
    <livewire:blog.post-grid :posts="$posts" />
</div>
