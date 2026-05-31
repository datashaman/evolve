<?php

namespace Tests\Feature;

use Database\Seeders\BlogPostSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_returns_a_successful_response(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
    }

    public function test_home_route_renders_blog_posts_from_content_model(): void
    {
        $this->seed(BlogPostSeeder::class);

        $this->assertDatabaseHas('blog_posts', [
            'title' => 'The power of MCP tools',
            'slug' => 'the-power-of-mcp-tools',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('The power of MCP tools');
    }

    public function test_blog_article_route_renders_seeded_content_model_post(): void
    {
        $this->seed(BlogPostSeeder::class);

        $this->get('/blog/the-power-of-mcp-tools')
            ->assertOk()
            ->assertSee('The power of MCP tools')
            ->assertSee('The server becomes a pure state holder and workflow system');
    }
}
