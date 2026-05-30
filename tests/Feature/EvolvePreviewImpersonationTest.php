<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EvolvePreviewImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/test-preview', function () {
            return response()->json([
                'id' => Auth::id(),
                'email' => optional(Auth::user())->email,
            ]);
        });
    }

    public function test_request_without_preview_as_runs_as_session_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/test-preview')
            ->assertOk()
            ->assertJson(['id' => $user->id, 'email' => $user->email]);
    }

    public function test_preview_as_swaps_to_target_user_for_the_request(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($workbenchUser)
            ->getJson('/test-preview?preview_as='.$target->id)
            ->assertOk()
            ->assertJson(['id' => $target->id, 'email' => $target->email]);
    }

    public function test_preview_as_is_rejected_when_impersonation_is_disabled(): void
    {
        config()->set('evolve.preview.allow_impersonation', false);

        $workbenchUser = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($workbenchUser)
            ->getJson('/test-preview?preview_as='.$target->id)
            ->assertForbidden();
    }

    public function test_preview_as_is_rejected_when_requester_is_not_authenticated(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $target = User::factory()->create();

        $this->getJson('/test-preview?preview_as='.$target->id)
            ->assertForbidden();
    }

    public function test_preview_as_unknown_user_is_rejected(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();

        $this->actingAs($workbenchUser)
            ->getJson('/test-preview?preview_as=999999')
            ->assertNotFound();
    }

    public function test_users_endpoint_returns_users_when_allowed(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Bob']);

        $response = $this->actingAs($workbenchUser)
            ->getJson('/api/preview/users')
            ->assertOk()
            ->json();

        $this->assertTrue($response['allow_impersonation']);
        $this->assertCount(2, $response['users']);
        $this->assertSame(['Alice', 'Bob'], array_column($response['users'], 'name'));
    }

    public function test_users_endpoint_returns_forbidden_when_disabled(): void
    {
        config()->set('evolve.preview.allow_impersonation', false);

        $workbenchUser = User::factory()->create();

        $this->actingAs($workbenchUser)
            ->getJson('/api/preview/users')
            ->assertForbidden();
    }
}
