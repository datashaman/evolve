<?php

namespace App\Services;

use App\Models\EvolveFeedback;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class EvolveFeedbackRepository
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function previewFeedback(array $data): array
    {
        return [
            'feedback' => $this->feedbackPayload($data),
            'would_write' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sendFeedback(array $data): array
    {
        $feedback = EvolveFeedback::query()->create($this->feedbackPayload($data));

        return [
            'feedback' => $this->serialize($feedback),
            'created' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function previewTriage(string $id, array $data): array
    {
        $feedback = EvolveFeedback::query()->findOrFail($id);

        return [
            'feedback' => [
                ...$this->serialize($feedback),
                ...$this->triagePayload($data),
            ],
            'would_write' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function triageFeedback(string $id, array $data): array
    {
        $feedback = EvolveFeedback::query()->findOrFail($id);
        $feedback->fill($this->triagePayload($data));
        $feedback->save();

        return [
            'feedback' => $this->serialize($feedback),
            'updated' => true,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return EvolveFeedback::query()
            ->newest()
            ->get()
            ->map(fn (EvolveFeedback $feedback): array => $this->serialize($feedback))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function feedbackPayload(array $data): array
    {
        return [
            'id' => $data['id'] ?? 'fb_'.Str::lower(Str::random(12)),
            'type' => $data['type'] ?? 'other',
            'message' => $data['message'],
            'source' => $data['source'] ?? 'mcp',
            'author' => $data['author'] ?? null,
            'url' => $data['url'] ?? null,
            'artifact_kind' => $data['artifact_kind'] ?? null,
            'artifact_id' => $data['artifact_id'] ?? null,
            'context' => $data['context'] ?? [],
            'status' => 'new',
            'priority' => null,
            'labels' => [],
            'triage_notes' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function triagePayload(array $data): array
    {
        $payload = Arr::only($data, ['status', 'priority', 'labels', 'assignee']);

        if (array_key_exists('notes', $data)) {
            $payload['triage_notes'] = $data['notes'];
        }

        $payload['triaged_at'] = now();

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(EvolveFeedback $feedback): array
    {
        return [
            'id' => $feedback->id,
            'type' => $feedback->type,
            'message' => $feedback->message,
            'source' => $feedback->source,
            'author' => $feedback->author,
            'url' => $feedback->url,
            'artifact_kind' => $feedback->artifact_kind,
            'artifact_id' => $feedback->artifact_id,
            'context' => $feedback->context ?? [],
            'status' => $feedback->status,
            'priority' => $feedback->priority,
            'labels' => $feedback->labels ?? [],
            'assignee' => $feedback->assignee,
            'triage_notes' => $feedback->triage_notes,
            'triaged_at' => $feedback->triaged_at?->toISOString(),
            'created_at' => $feedback->created_at?->toISOString(),
            'updated_at' => $feedback->updated_at?->toISOString(),
        ];
    }
}
