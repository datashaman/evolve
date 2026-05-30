<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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

        Route::middleware('web')->get('/test-preview-html', function () {
            return response('<!doctype html><html><body><p>hi</p></body></html>')
                ->header('Content-Type', 'text/html');
        });

        Route::middleware(['web', 'guest'])->get('/test-preview-guest-only', function () {
            return response('guest-ok');
        });

        Route::middleware(['web', 'auth'])->get('/test-preview-auth-only', function () {
            return response('auth-ok');
        });

        Route::middleware(['web', 'auth'])->get('/workbench/preview/test', function () {
            return response('internal-preview-ok');
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

    public function test_preview_as_via_query_swaps_to_target_user(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($workbenchUser)
            ->getJson('/test-preview?preview_as='.$target->id)
            ->assertOk()
            ->assertJson(['id' => $target->id, 'email' => $target->email]);
    }

    public function test_preview_as_via_header_swaps_to_target_user(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($workbenchUser)
            ->getJson('/test-preview', ['X-Preview-As' => (string) $target->id])
            ->assertOk()
            ->assertJson(['id' => $target->id, 'email' => $target->email]);
    }

    public function test_preview_as_guest_runs_as_guest_for_one_request(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();

        $this->actingAs($workbenchUser)
            ->getJson('/test-preview?preview_as=guest')
            ->assertOk()
            ->assertJson(['id' => null, 'email' => null]);

        $this->getJson('/test-preview')
            ->assertOk()
            ->assertJson(['id' => $workbenchUser->id, 'email' => $workbenchUser->email]);
    }

    public function test_preview_as_guest_allows_guest_only_routes(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();

        $this->actingAs($workbenchUser)
            ->get('/test-preview-guest-only?preview_as=guest')
            ->assertOk()
            ->assertSee('guest-ok');
    }

    public function test_preview_as_guest_can_render_real_login_route(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();

        $this->actingAs($workbenchUser)
            ->get('/login?preview_as=guest')
            ->assertOk();
    }

    public function test_preview_as_guest_does_not_lock_internal_preview_route(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();

        $this->actingAs($workbenchUser)
            ->get('/workbench/preview/test?preview_as=guest')
            ->assertOk()
            ->assertSee('internal-preview-ok');
    }

    public function test_preview_as_guest_can_render_welcome_view_preview(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();

        $this->actingAs($workbenchUser)
            ->get('/workbench/preview/view/welcome?preview_as=guest')
            ->assertOk();
    }

    public function test_preview_as_guest_survives_auth_redirects(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();

        $this->actingAs($workbenchUser)
            ->get('/test-preview-auth-only?preview_as=guest')
            ->assertRedirect('/login?preview_as=guest');
    }

    public function test_preview_as_user_survives_redirects(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();
        $target = User::factory()->create();

        Route::middleware('web')->get('/test-preview-redirect', function () {
            return redirect('/test-preview-html');
        });

        $this->actingAs($workbenchUser)
            ->get('/test-preview-redirect?preview_as='.$target->id)
            ->assertRedirect('/test-preview-html?preview_as='.$target->id);
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

    public function test_preview_as_respects_impersonation_gate(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();
        $target = User::factory()->create();

        Gate::define('evolve.preview.impersonate', fn (User $auth, User $candidate): bool => false);

        $this->actingAs($workbenchUser)
            ->getJson('/test-preview?preview_as='.$target->id)
            ->assertForbidden();
    }

    public function test_impersonation_is_logged(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();
        $target = User::factory()->create();

        Log::spy();

        $this->actingAs($workbenchUser)
            ->getJson('/test-preview?preview_as='.$target->id)
            ->assertOk();

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context) use ($workbenchUser, $target): bool {
                return $message === 'evolve.preview.impersonation'
                    && $context['workbench_user_id'] === $workbenchUser->id
                    && $context['target_user_id'] === $target->id
                    && $context['source'] === 'query';
            })
            ->once();
    }

    public function test_html_response_gets_preview_hook_script_injected(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();
        $target = User::factory()->create();

        $body = $this->actingAs($workbenchUser)
            ->get('/test-preview-html?preview_as='.$target->id)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-evolve-preview-hook', $body);
        $this->assertStringContainsString('X-Preview-As', $body);
        $this->assertStringContainsString('var previewAs = "'.$target->id.'"', $body);
    }

    public function test_guest_html_response_gets_preview_hook_script_injected(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        $workbenchUser = User::factory()->create();

        $body = $this->actingAs($workbenchUser)
            ->get('/test-preview-html?preview_as=guest')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-evolve-preview-hook', $body);
        $this->assertStringContainsString('X-Preview-As', $body);
        $this->assertStringContainsString('var previewAs = "guest"', $body);
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

    public function test_preview_controller_route_carries_impersonation(): void
    {
        config()->set('evolve.preview.allow_impersonation', true);

        Route::middleware(['web', 'auth', 'verified'])->get('/test-preview-controller', function () {
            return response('<!doctype html><html><body data-user="'.Auth::id().'"></body></html>')
                ->header('Content-Type', 'text/html');
        });

        $workbenchUser = User::factory()->create(['email_verified_at' => now()]);
        $target = User::factory()->create(['email_verified_at' => now()]);

        $body = $this->actingAs($workbenchUser)
            ->get('/test-preview-controller?preview_as='.$target->id)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-user="'.$target->id.'"', $body);
        $this->assertStringContainsString('X-Preview-As', $body, 'expected fetch hook to be injected');
    }
}
