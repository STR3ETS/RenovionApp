<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use Illuminate\Notifications\Notification;

/**
 * Iemand anders (of Nova namens iemand) zet een taak op jouw naam.
 */
class TaskAssigned extends Notification
{
    public function __construct(public Task $task, public User $assignedBy) {}

    public function type(): NotificationType
    {
        return NotificationType::TaskAssigned;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', PushChannel::class];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        $context = $this->task->project?->name ?? $this->task->customer?->name;
        $deadline = $this->task->deadline
            ? ($this->task->deadline->isToday() ? 'vandaag' : $this->task->deadline->translatedFormat('D j M'))
            : null;

        return [
            'title' => str($this->assignedBy->name)->before(' ').' gaf je een taak',
            'body' => collect([$this->task->title, $context, $deadline ? 'deadline '.$deadline : null])->filter()->implode(', '),
            'url' => route('tasks.index', absolute: false),
            'icon' => 'clipboard-check',
        ];
    }
}
