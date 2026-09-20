<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Support\DashboardDate;
use App\Support\TaskAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyWorkDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $today = DashboardDate::now()->toDateString();
        $assigned = TaskAccess::scopeTasks(Task::query(), $user)->where('assignee_id', $user->id);
        $open = (clone $assigned)->whereIn('status', ['todo', 'in_progress', 'blocked']);
        $items = (clone $open)->with('project:id,name')
            ->orderBy('due_date')->limit(30)
            ->get(['id', 'project_id', 'title', 'status', 'priority', 'due_date'])
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'project_id' => $task->project_id,
                'project_name' => $task->project?->name ?? 'No project',
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
            ]);

        return Inertia::render('Delivery/MyWorkDashboard', [
            'summary' => [
                'due_today' => (clone $open)->whereDate('due_date', $today)->count(),
                'overdue' => (clone $open)->whereDate('due_date', '<', $today)->count(),
                'completed_this_week' => DashboardDate::range((clone $assigned)->where('status', 'done'), 'completed_at', DashboardDate::now()->startOfWeek()->toDateString(), DashboardDate::now()->endOfWeek()->toDateString())->count(),
            ],
            'groups' => $items->groupBy(fn ($item) => $item['project_id'] ?? 'no-project')
                ->map(fn ($tasks) => ['project_id' => $tasks->first()['project_id'], 'project_name' => $tasks->first()['project_name'], 'tasks' => $tasks->values()])
                ->values(),
            'hasMore' => (clone $open)->count() > 30,
        ]);
    }
}
