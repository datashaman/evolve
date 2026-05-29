<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Services\EvolveContentModelScaffolder;
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

    public function test_workbench_content_api_deletes_services_missing_from_payload(): void
    {
        $this->actingAs(User::factory()->create([
            'email_verified_at' => now(),
        ]));

        $kept = Service::query()->create([
            'icon' => '01',
            'title' => 'Keep this',
            'summary' => 'Still present.',
            'position' => 1,
            'is_published' => true,
        ]);

        $deleted = Service::query()->create([
            'icon' => '02',
            'title' => 'Delete this',
            'summary' => 'Removed from the table.',
            'position' => 2,
            'is_published' => true,
        ]);

        $this->putJson('/api/content', [
            'services' => [
                [
                    'id' => (string) $kept->id,
                    'icon' => '01',
                    'title' => 'Keep this',
                    'summary' => 'Still present.',
                    'position' => 1,
                    'is_published' => true,
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('services', ['id' => $kept->id]);
        $this->assertDatabaseMissing('services', ['id' => $deleted->id]);
    }

    public function test_workbench_content_model_endpoint_invokes_scaffolder(): void
    {
        $this->actingAs(User::factory()->create([
            'email_verified_at' => now(),
        ]));

        $this->mock(EvolveContentModelScaffolder::class)
            ->shouldReceive('create')
            ->once()
            ->with('Case Study');

        $this->postJson('/api/content/models', [
            'name' => 'Case Study',
        ])->assertOk();
    }
}
