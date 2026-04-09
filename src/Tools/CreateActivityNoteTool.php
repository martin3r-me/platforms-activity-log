<?php

namespace Platform\ActivityLog\Tools;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

class CreateActivityNoteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'activity_log.activities.POST';
    }

    public function getDescription(): string
    {
        return 'POST /activity_log/activities - Erstellt eine manuelle Notiz/Activity für ein beliebiges Model. '
            . 'Benötigt entity_type (Morph-Alias wie "planner_task", "project" oder FQCN), entity_id und message. '
            . 'Optional: metadata (Key-Value-Daten).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_type' => [
                    'type' => 'string',
                    'description' => 'Morph-Alias (z.B. "planner_task", "project") oder FQCN des Models. ERFORDERLICH.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Datensatzes. ERFORDERLICH.',
                ],
                'message' => [
                    'type' => 'string',
                    'description' => 'Die Notiz / der Kommentar. ERFORDERLICH.',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optionale zusätzliche Key-Value-Daten.',
                ],
            ],
            'required' => ['entity_type', 'entity_id', 'message'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $user = $context->user;
            if (!$user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $entityType = $arguments['entity_type'] ?? null;
            $entityId = $arguments['entity_id'] ?? null;
            $message = trim((string) ($arguments['message'] ?? ''));
            $metadata = $arguments['metadata'] ?? [];

            if (!$entityType || !$entityId) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_type und entity_id sind erforderlich.');
            }

            if ($message === '') {
                return ToolResult::error('VALIDATION_ERROR', 'message darf nicht leer sein.');
            }

            $model = $this->resolveModel($entityType, (int) $entityId);
            if ($model instanceof ToolResult) {
                return $model;
            }

            $model->logActivity($message, is_array($metadata) ? $metadata : []);

            // Fetch the just-created activity
            $activity = $model->activities()->latest()->first();

            return ToolResult::success([
                'activity' => [
                    'id' => $activity->id,
                    'uuid' => $activity->uuid,
                    'activity_type' => $activity->activity_type,
                    'name' => $activity->name,
                    'message' => $activity->message,
                    'metadata' => $activity->metadata,
                    'created_at' => $activity->created_at?->toIso8601String(),
                ],
                'message' => 'Notiz erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Notiz: ' . $e->getMessage());
        }
    }

    /**
     * Resolve entity_type + entity_id to a model instance.
     *
     * @return \Illuminate\Database\Eloquent\Model|ToolResult
     */
    protected function resolveModel(string $entityType, int $entityId)
    {
        // 1. Try morph alias
        $class = Relation::getMorphedModel($entityType);

        // 2. Fallback: FQCN
        if (!$class && class_exists($entityType)) {
            $class = $entityType;
        }

        if (!$class || !class_exists($class)) {
            return ToolResult::error('VALIDATION_ERROR', "Unbekannter entity_type: {$entityType}. Nutze einen Morph-Alias (z.B. 'planner_task') oder FQCN.");
        }

        if (!method_exists($class, 'activities')) {
            return ToolResult::error('VALIDATION_ERROR', "Das Model {$entityType} unterstützt keine Activities (LogsActivity Trait fehlt).");
        }

        $model = $class::find($entityId);
        if (!$model) {
            return ToolResult::error('NOT_FOUND', "{$entityType} mit ID {$entityId} nicht gefunden.");
        }

        return $model;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['activity_log', 'activities', 'create', 'note'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => false,
            'examples' => [
                'Notiz für Planner-Task 42: entity_type="planner_task", entity_id=42, message="Status-Update: läuft planmäßig"',
                'Notiz mit Metadata: metadata={"source": "meeting", "priority": "high"}',
            ],
            'related_tools' => ['activity_log.activities.GET'],
        ];
    }
}
