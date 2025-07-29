<?php

namespace Platform\ActivityLog\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Platform\ActivityLog\Models\ActivityLogActivity;

trait LogsActivity
{
    /**
     * Model-scoped override for the events to record.
     * e.g. protected static array $recordEvents = ['created', 'updated', 'deleted'];
     * If empty, falls back to config('activity-log.events').
     *
     * @var string[]
     */
    protected static array $recordEvents = [];

    /**
     * Instance-scoped attributes to ignore when recording changes.
     * e.g. protected array $ignoreAttributes = ['password', 'remember_token'];
     * Merged with config('activity-log.ignore_attributes').
     *
     * @var string[]
     */
    protected array $ignoreAttributes = [];

    /**
     * Boot the LogsActivity trait and register model event listeners.
     */
    public static function bootLogsActivity(): void
    {
        $events = static::$recordEvents ?: config('activity-log.events', []);

        foreach ($events as $event) {
            static::{$event}(function (Model $model) use ($event) {
                // Record as system type
                $model->recordActivity($event, 'system');
            });
        }
    }

    /**
     * Polymorphic activities relation with latest ordering.
     */
    public function activities()
    {
        return $this->morphMany(ActivityLogActivity::class, 'activityable')->latest();
    }

    /**
     * Record an activity with given event name and type.
     */
    public function recordActivity(string $event, string $activityType): void
    {
        $properties = $this->getActivityProperties($event);
        if ($event === 'updated' && empty($properties)) {
            return;
        }

        $this->activities()->create([
            'activity_type' => $activityType,
            'name'          => $event,
            'user_id'       => auth()->id(),
            'properties'    => $properties,
        ]);
    }

    /**
     * Convenience alias for manual activities.
     */
    public function logActivity(string $message): void
    {
        $this->recordActivity('manual', 'manual');
    }

    /**
     * Gather the properties to save for the activity.
     */
    protected function getActivityProperties(string $event): array
    {
        $attrs = $event === 'updated' ? $this->getChanges() : $this->getAttributes();
        $ignore = array_merge(config('activity-log.ignore_attributes', []), $this->ignoreAttributes);
        return collect($attrs)->except($ignore)->toArray();
    }
}