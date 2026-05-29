<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvolveContentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'services' => Service::query()
                ->ordered()
                ->get()
                ->map(fn (Service $service) => $this->servicePayload($service))
                ->values(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $services = collect($request->input('services', []))
            ->map(function (array $payload, int $index): array {
                $service = is_numeric($payload['id'] ?? null)
                    ? Service::query()->findOrNew((int) $payload['id'])
                    : new Service;

                $service->fill([
                    'icon' => (string) ($payload['icon'] ?? ''),
                    'title' => (string) ($payload['title'] ?? ''),
                    'summary' => (string) ($payload['summary'] ?? ''),
                    'position' => (int) ($payload['position'] ?? $index + 1),
                    'is_published' => (bool) ($payload['is_published'] ?? false),
                ]);
                $service->save();

                return [
                    'client_id' => (string) ($payload['id'] ?? ''),
                    'service' => $service,
                ];
            })
            ->sortBy(fn (array $entry) => $entry['service']->position)
            ->map(fn (array $entry) => [
                ...$this->servicePayload($entry['service']),
                'client_id' => $entry['client_id'],
            ])
            ->values();

        return response()->json(['services' => $services]);
    }

    protected function servicePayload(Service $service): array
    {
        return [
            'id' => (string) $service->id,
            'kind' => 'content',
            'model' => 'Service',
            'name' => $service->title,
            'title' => $service->title,
            'icon' => $service->icon,
            'summary' => $service->summary,
            'position' => $service->position,
            'is_published' => $service->is_published,
            'path' => 'app/Models/Service.php',
        ];
    }
}
