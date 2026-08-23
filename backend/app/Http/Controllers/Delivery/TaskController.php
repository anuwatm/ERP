<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\ProjectAccess;
use App\Support\TaskAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'project_id', 'assignee_id']);
        $canSeeAll = TaskAccess::canSeeAll($user);

        $tasks = TaskAccess::scopeTasks(Task::query(), $user)
            ->with([
                'project:id,project_code,name,owner_id',
                'assignee:id,name,email',
                'checklists:id,task_id,title,is_done,sort_order',
                'comments.user:id,name,email',
            ])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhereHas('project', fn ($project) => $project->where('name', 'like', "%{$search}%"));
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['project_id'] ?? null, fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->when(($filters['assignee_id'] ?? null) && $canSeeAll, fn ($query) => $query->where('assignee_id', $filters['assignee_id']))
            ->orderByRaw("CASE status WHEN 'todo' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'blocked' THEN 3 WHEN 'done' THEN 4 ELSE 5 END")
            ->orderBy('due_date')
            ->latest()
            ->get();

        return Inertia::render('Delivery/Tasks', [
            'tasks' => $tasks,
            'projects' => ProjectAccess::scopeProjects(Project::query(), $user)->orderBy('name')->get(['id', 'project_code', 'name', 'owner_id']),
            'assignees' => User::where('org_id', $user->org_id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
            'statuses' => Task::STATUSES,
            'priorities' => Task::PRIORITIES,
            'filters' => $filters,
            'canSeeAllTasks' => $canSeeAll,
            'canCreateTasks' => $user->hasPermissionCode('tasks.create'),
            'canUpdateTasks' => $user->hasPermissionCode('tasks.update'),
            'canCommentTasks' => $user->hasPermissionCode('tasks.comment'),
        ]);
    }

    public function store(Request $request, NotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateTask($request);
        $project = $this->projectFor($validated['project_id'] ?? null, $user);
        abort_unless(TaskAccess::canManageProjectTasks($user, $project), 403);

        $validated['org_id'] = $user->org_id;
        $validated['assignee_id'] = $validated['assignee_id'] ?: $user->id;
        $validated['completed_at'] = $validated['status'] === 'done' ? now() : null;
        $validated['created_by'] = $user->id;

        $task = Task::create($validated);
        $this->audit($request, 'task.create', $task, null, $this->snapshot($task));
        $this->notifyAssignee($task, $notifications);

        return back()->with('success', 'Task created.');
    }

    public function update(Request $request, Task $task, NotificationService $notifications): RedirectResponse
    {
        $user = $request->user();
        $task->load('project');
        TaskAccess::assertTaskVisible($task, $user);
        $before = $this->snapshot($task);
        $validated = $this->validateTask($request, $task);
        $project = $this->projectFor($validated['project_id'] ?? null, $user);
        $canManage = TaskAccess::canManageProjectTasks($user, $project);

        if (! $canManage) {
            $validated = array_merge($task->only(['project_id', 'title', 'description', 'priority', 'assignee_id', 'due_date']), [
                'status' => $validated['status'],
            ]);
        }

        $validated['completed_at'] = $validated['status'] === 'done' ? ($task->completed_at ?? now()) : null;
        $validated['updated_by'] = $user->id;
        $task->update($validated);
        $this->audit($request, 'task.update', $task, $before, $this->snapshot($task));
        $this->notifyAssignee($task, $notifications);

        return back()->with('success', 'Task updated.');
    }

    public function storeChecklist(Request $request, Task $task): RedirectResponse
    {
        $user = $request->user();
        $task->load('project');
        TaskAccess::assertTaskVisible($task, $user);
        $validated = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $checklist = TaskChecklist::create([
            'org_id' => $task->org_id,
            'task_id' => $task->id,
            'title' => $validated['title'],
            'sort_order' => (int) $task->checklists()->max('sort_order') + 1,
            'created_by' => $user->id,
        ]);
        $this->audit($request, 'task_checklist.create', $task, null, $checklist->only(['id', 'title', 'is_done', 'sort_order']));

        return back()->with('success', 'Checklist added.');
    }

    public function toggleChecklist(Request $request, TaskChecklist $checklist): RedirectResponse
    {
        $user = $request->user();
        $checklist->load('task.project');
        abort_unless($checklist->org_id === $user->org_id, 404);
        TaskAccess::assertTaskVisible($checklist->task, $user);
        $validated = $request->validate(['is_done' => ['required', 'boolean']]);
        $before = $checklist->only(['id', 'title', 'is_done']);

        $checklist->update(['is_done' => $validated['is_done'], 'updated_by' => $user->id]);
        $this->audit($request, 'task_checklist.update', $checklist->task, $before, $checklist->fresh()->only(['id', 'title', 'is_done']));

        return back()->with('success', 'Checklist updated.');
    }

    public function storeComment(Request $request, Task $task): RedirectResponse
    {
        $user = $request->user();
        $task->load('project');
        TaskAccess::assertTaskVisible($task, $user);
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $comment = TaskComment::create([
            'org_id' => $task->org_id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);
        $this->audit($request, 'task_comment.create', $task, null, $comment->only(['id', 'user_id', 'body']));

        return back()->with('success', 'Comment added.');
    }

    private function validateTask(Request $request, ?Task $task = null): array
    {
        return $request->validate([
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('org_id', $request->user()->org_id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(Task::STATUSES)],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'assignee_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('org_id', $request->user()->org_id)],
            'due_date' => ['nullable', 'date'],
        ]);
    }

    private function projectFor(?string $projectId, User $user): ?Project
    {
        if (! $projectId) {
            return null;
        }

        return Project::where('org_id', $user->org_id)->whereKey($projectId)->firstOrFail();
    }

    private function snapshot(Task $task): array
    {
        return $task->fresh()->only(['project_id', 'title', 'description', 'status', 'priority', 'assignee_id', 'due_date', 'completed_at']);
    }

    private function notifyAssignee(Task $task, NotificationService $notifications): void
    {
        if (! $task->assignee_id) {
            return;
        }

        $assignee = User::where('org_id', $task->org_id)->whereKey($task->assignee_id)->first();
        if (! $assignee) {
            return;
        }

        $notifications->notify(
            $assignee,
            'task.assigned',
            "task.assigned:{$task->id}:{$assignee->id}",
            'Task assigned',
            $task->title,
            route('tasks.index')
        );
    }

    private function audit(Request $request, string $action, Task $task, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $task->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'task',
            'entity_id' => $task->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
