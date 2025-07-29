<?php

namespace Platform\ActivityLog\Http\Livewire\Activities;

use Livewire\Component;
use Platform\ActivityLog\Models\ActivityLogActivity;

class Index extends Component
{
    public $model;
    public $message;

    public function render()
    {
        $activities = blank($this->model)
            ? collect()
            : $this->model->activities()->latest()->get();

        return view('activity-log::livewire.activities.index', compact('activities'));
    }

    public function save(): void
    {
        if (blank($this->model) || blank($this->message)) {
            return;
        }

        $userId = auth()->id();

        $this->model->activities()->create([
            'activity_type' => 'manual',
            'name'          => 'message_added',
            'description'   => 'User-created message',
            'user_id'       => $userId,
            'message'       => trim($this->message),
            'metadata'      => null,
        ]);

        $this->reset('message');
        $this->model->refresh();
    }
}