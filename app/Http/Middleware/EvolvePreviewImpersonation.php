<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EvolvePreviewImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        $previewAs = $request->query('preview_as');

        if ($previewAs === null || $previewAs === '') {
            return $next($request);
        }

        abort_unless(config('evolve.preview.allow_impersonation'), 403, 'Preview impersonation is disabled.');
        abort_unless(Auth::check(), 403, 'Preview impersonation requires an authenticated workbench session.');

        $user = User::find($previewAs);
        abort_unless($user, 404, 'Preview target user not found.');

        Auth::onceUsingId($user->getKey());

        return $next($request);
    }
}
