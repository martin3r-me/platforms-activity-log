<?php

namespace Platform\ActivityLog\Tools;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;

class ListActivitiesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'activity_log.activities.GET';
    }

    public function getDescription(): string
    {
        return 'GET /activity_log/activities - Listet Activities (System-Events und manuelle Notizen) für ein beliebiges Model. '
            . 'Benötigt entity_type (Morph-Alias wie "planner_task", "project" oder FQCN) und entity_id. '
            . 'Optional: activity_type Filter ("system", "manual"). '
            . 'Standard-Filter, Suche, Sortierung und Pagination verfügbar.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'entity_type' => [
                        'type' => 'string',
                        'description' => 'Morph-Alias (z.B. "planner_task", "project") oder FQCN des Models. ERFORDERLICH.',
                    ],
                    'entity_id' => [
                        'type' => 'integer',
                        'description' => 'ID des Datensatzes. ERFORDERLICH.',
                    ],
                    'activity_type' => [
                        'type' => 'string',
                        'description' => 'Filter nach Activity-Typ: "system" (automatische Events), "manual" (Notizen), oder weglassen für alle.',
                    ],
                ],
                'required' => ['entity_type', 'entity_id'],
            ]
        );
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

            if (!$entityType || !$entityId) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_type und entity_id sind erforderlich.');
            }

            $model = $this->resolveModel($entityType, (int) $entityId);
            if ($model instanceof ToolResult) {
                return $model;
            }

            $query = $model->activities();

            // Activity-Type Filter
            if (!empty($arguments['activity_type'])) {
                $query->where('activity_type', $arguments['activity_type']);
            }

            // Standard-Filter
            $this->applyStandardFilters($query, $arguments, [
                'activity_type', 'name', 'user_id', 'created_at', 'updated_at',
            ]);

            // Suche
            $this->applyStandardSearch($query, $arguments, ['message', 'name']);

            // Sortierung (default: neueste zuerst)
            $this->applyStandardSort($query, $arguments, [
                'created_at', 'updated_at', 'activity_type', 'name', 'id',
            ], 'created_at', 'desc');

            // Pagination
            $paginationResult = $this->applyStandardPaginationResult($query, $arguments);
            $activities = $paginationResult['data'];

            // Eager-load users
            $activities->load('user:id,name');

            $formatted = $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'uuid' => $activity->uuid,
                    'activity_type' => $activity->activity_type,
                    'name' => $activity->name,
                    'message' => $activity->message,
                    'user' => $activity->user ? [
                        'id' => $activity->user->id,
                        'name' => $activity->user->name,
                    ] : null,
                    'properties' => $activity->properties,
                    'metadata' => $activity->metadata,
                    'created_at' => $activity->created_at?->toIso8601String(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'activities' => $formatted,
                'count' => count($formatted),
                'pagination' => $paginationResult['pagination'],
                'entity_type' => $entityType,
                'entity_id' => (int) $entityId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Activities: ' . $e->getMessage());
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
            'category' => 'query',
            'tags' => ['activity_log', 'activities', 'list', 'notes'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
            'examples' => [
                'Zeige alle Activities für Planner-Task 42: entity_type="planner_task", entity_id=42',
                'Nur manuelle Notizen: activity_type="manual"',
                'Nur System-Events: activity_type="system"',
            ],
            'related_tools' => ['activity_log.activities.POST'],
        ];
    }
}
