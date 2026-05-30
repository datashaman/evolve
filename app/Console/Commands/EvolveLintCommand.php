<?php

namespace App\Console\Commands;

use App\Services\EvolveLibrary;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;

class EvolveLintCommand extends Command
{
    protected $signature = 'evolve:lint {--json : Emit findings as JSON}';

    protected $description = 'Audit page and form artifacts for route name collisions and unknown middleware aliases.';

    public function handle(EvolveLibrary $library, Router $router): int
    {
        $findings = [];
        $artifactRoutes = $library->artifactRoutes();

        $findings = array_merge(
            $findings,
            $this->checkDuplicateRouteNames($artifactRoutes),
            $this->checkStaticRouteCollisions($artifactRoutes, $router),
            $this->checkUnknownMiddleware($artifactRoutes, $router),
        );

        if ($this->option('json')) {
            $this->line(json_encode(['findings' => $findings], JSON_PRETTY_PRINT));

            return $findings === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($findings === []) {
            $this->info('evolve:lint — no findings.');

            return self::SUCCESS;
        }

        $this->table(
            ['Severity', 'Code', 'Subject', 'Message'],
            collect($findings)->map(fn (array $finding): array => [
                $finding['severity'],
                $finding['code'],
                $finding['subject'],
                $finding['message'],
            ])->all(),
        );

        return self::FAILURE;
    }

    protected function checkDuplicateRouteNames(array $artifactRoutes): array
    {
        $findings = [];
        $byName = collect($artifactRoutes)
            ->filter(fn (array $route): bool => filled($route['route_name'] ?? ''))
            ->groupBy('route_name');

        foreach ($byName as $name => $entries) {
            if ($entries->count() < 2) {
                continue;
            }

            $findings[] = [
                'severity' => 'error',
                'code' => 'duplicate-route-name',
                'subject' => (string) $name,
                'message' => 'Multiple artifacts share this route name: '.$entries->pluck('component')->implode(', '),
            ];
        }

        return $findings;
    }

    protected function checkStaticRouteCollisions(array $artifactRoutes, Router $router): array
    {
        $findings = [];

        foreach ($artifactRoutes as $route) {
            if (! filled($route['route_name'] ?? '')) {
                continue;
            }

            $expectedUri = ltrim($route['route'], '/');
            $colliders = collect($router->getRoutes())
                ->filter(fn ($registered): bool => $registered->getName() === $route['route_name'])
                ->reject(fn ($registered): bool => $registered->uri() === $expectedUri || '/'.$registered->uri() === $route['route']);

            if ($colliders->isNotEmpty()) {
                $findings[] = [
                    'severity' => 'error',
                    'code' => 'shadows-static-route',
                    'subject' => $route['route_name'],
                    'message' => "Artifact route name shadows another route registered with the same name (artifact {$route['component']} → {$route['route']}).",
                ];
            }
        }

        return $findings;
    }

    protected function checkUnknownMiddleware(array $artifactRoutes, Router $router): array
    {
        $registered = array_merge(
            array_keys($router->getMiddleware()),
            array_keys($router->getMiddlewareGroups()),
        );

        $findings = [];
        foreach ($artifactRoutes as $route) {
            foreach ($route['middleware'] ?? [] as $middleware) {
                $alias = explode(':', $middleware, 2)[0];

                if ($alias === '' || in_array($alias, $registered, true) || class_exists($alias)) {
                    continue;
                }

                $findings[] = [
                    'severity' => 'warning',
                    'code' => 'unknown-middleware',
                    'subject' => $middleware,
                    'message' => "Unknown middleware alias on {$route['component']} ({$route['route']}). Register it in bootstrap/app.php or correct the spelling.",
                ];
            }
        }

        return $findings;
    }
}
