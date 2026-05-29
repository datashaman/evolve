<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_published_services_from_database(): void
    {
        Service::query()->create([
            'icon' => 'A',
            'title' => 'Dynamic service',
            'summary' => 'Rendered from a native Eloquent model.',
            'position' => 1,
            'is_published' => true,
        ]);

        Service::query()->create([
            'icon' => 'B',
            'title' => 'Hidden service',
            'summary' => 'This should not be rendered.',
            'position' => 2,
            'is_published' => false,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Dynamic service')
            ->assertSee('Rendered from a native Eloquent model.')
            ->assertDontSee('Hidden service');
    }

    public function test_workbench_content_api_updates_services(): void
    {
        $this->actingAs(User::factory()->create([
            'email_verified_at' => now(),
        ]));

        $response = $this->putJson('/api/content', [
            'services' => [
                [
                    'icon' => '01',
                    'title' => 'Edited service',
                    'summary' => 'Edited through the workbench API.',
                    'position' => 1,
                    'is_published' => true,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('services.0.title', 'Edited service')
            ->assertJsonPath('services.0.client_id', '');

        $this->assertDatabaseHas('services', [
            'title' => 'Edited service',
            'summary' => 'Edited through the workbench API.',
        ]);

        $id = $response->json('services.0.id');

        $this->putJson('/api/content', [
            'services' => [
                [
                    'id' => $id,
                    'icon' => '02',
                    'title' => 'Edited again',
                    'summary' => 'Updated without creating a duplicate.',
                    'position' => 1,
                    'is_published' => true,
                ],
            ],
        ])->assertOk();

        $this->assertSame(1, Service::query()->count());
        $this->assertDatabaseHas('services', [
            'id' => $id,
            'title' => 'Edited again',
        ]);
    }
}
