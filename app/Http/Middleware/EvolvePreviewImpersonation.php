<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EvolvePreviewImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        $previewAs = $request->query('preview_as') ?? $request->header('X-Preview-As');

        if ($previewAs === null || $previewAs === '') {
            return $next($request);
        }

        if ($request->is('workbench/preview/*')) {
            return $next($request);
        }

        abort_unless((bool) config('evolve.preview.allow_impersonation'), 403, 'Preview impersonation is disabled.');
        abort_unless(Auth::check(), 403, 'Preview impersonation requires an authenticated workbench session.');

        $workbenchUser = Auth::user();

        if ($previewAs === 'guest') {
            return $this->handleGuestPreview($request, $next, $workbenchUser);
        }

        $target = User::find($previewAs);
        abort_unless($target, 404, 'Preview target user not found.');

        abort_unless(
            Gate::forUser($workbenchUser)->allows('evolve.preview.impersonate', $target),
            403,
            'You are not allowed to impersonate this user.',
        );

        Auth::onceUsingId($target->getKey());

        Log::info('evolve.preview.impersonation', [
            'workbench_user_id' => $workbenchUser->getKey(),
            'target_user_id' => $target->getKey(),
            'ip' => $request->ip(),
            'path' => $request->path(),
            'source' => $request->query('preview_as') !== null ? 'query' : 'header',
        ]);

        $response = $next($request);

        $this->carryPreviewIdentityThroughRedirect($request, $response, (string) $target->getKey());
        $this->injectPreviewHook($response, (string) $target->getKey());

        return $response;
    }

    protected function handleGuestPreview(Request $request, Closure $next, User $workbenchUser): Response
    {
        $guard = Auth::guard();
        $sessionKey = method_exists($guard, 'getName') ? $guard->getName() : null;
        $session = $request->hasSession() ? $request->session() : null;
        $hadSessionUser = $sessionKey !== null && $session?->has($sessionKey);
        $sessionUser = $hadSessionUser ? $session->get($sessionKey) : null;

        if ($sessionKey !== null && $session !== null) {
            $session->remove($sessionKey);
        }

        Auth::forgetGuards();

        Log::info('evolve.preview.impersonation', [
            'workbench_user_id' => $workbenchUser->getKey(),
            'target_user_id' => null,
            'target' => 'guest',
            'ip' => $request->ip(),
            'path' => $request->path(),
            'source' => $request->query('preview_as') !== null ? 'query' : 'header',
        ]);

        try {
            $response = $next($request);
        } finally {
            if ($sessionKey !== null && $session !== null) {
                $hadSessionUser
                    ? $session->put($sessionKey, $sessionUser)
                    : $session->remove($sessionKey);
            }

            Auth::forgetGuards();
            Auth::guard()->setUser($workbenchUser);
        }

        $this->carryPreviewIdentityThroughRedirect($request, $response, 'guest');
        $this->injectPreviewHook($response, 'guest');

        return $response;
    }

    protected function carryPreviewIdentityThroughRedirect(Request $request, Response $response, string $targetId): void
    {
        if (! $response->isRedirection()) {
            return;
        }

        $location = $response->headers->get('Location');
        if (! is_string($location) || $location === '') {
            return;
        }

        if (str_contains($location, 'preview_as=')) {
            return;
        }

        $appUrl = $request->getSchemeAndHttpHost();
        if (! str_starts_with($location, '/') && ! str_starts_with($location, $appUrl)) {
            return;
        }

        $response->headers->set(
            'Location',
            $location.(str_contains($location, '?') ? '&' : '?').'preview_as='.rawurlencode($targetId),
        );
    }

    protected function injectPreviewHook(Response $response, string $targetId): void
    {
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        $content = $response->getContent();
        if (! is_string($content) || stripos($content, '</body>') === false) {
            return;
        }

        $escaped = htmlspecialchars($targetId, ENT_QUOTES, 'UTF-8');
        $script = <<<HTML
<script data-evolve-preview-hook>
(function () {
  var previewAs = "{$escaped}";
  if (!previewAs) return;
  var originalFetch = window.fetch;
  window.fetch = function (input, init) {
    init = init || {};
    var url = typeof input === "string" ? input : (input && input.url) || "";
    if (url && /\/livewire\//.test(url)) {
      var headers = new Headers(init.headers || (input && input.headers) || {});
      if (!headers.has("X-Preview-As")) headers.set("X-Preview-As", previewAs);
      init.headers = headers;
    }
    return originalFetch.call(this, input, init);
  };
  if (window.Livewire && typeof window.Livewire.hook === "function") {
    window.Livewire.hook("request", function (payload) {
      var options = payload && payload.options;
      if (!options) return;
      options.headers = options.headers || {};
      if (!options.headers["X-Preview-As"]) options.headers["X-Preview-As"] = previewAs;
    });
  }
})();
</script>
HTML;

        $response->setContent(preg_replace('#</body>#i', $script."\n</body>", $content, 1));
    }
}
