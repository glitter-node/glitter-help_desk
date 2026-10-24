<?php

namespace Modules\Glitter\HelpDesk\Http\Controllers;

use App\Enums\PermissionType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Glitter\HelpDesk\Models\CannedReply;
use Modules\Glitter\HelpDesk\Models\Category;
use Modules\Glitter\HelpDesk\Models\Ticket;
use Modules\Glitter\HelpDesk\Services\TicketService;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $tickets) {}

    public function categories()
    {
        return response()->json(['data' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'parent_id'])]);
    }

    public function ownIndex(Request $request)
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'])], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = Ticket::query()->where('user_id', $request->user()->id)->with('category')->latest('updated_at');
        if (! empty($validated['q'])) {
            $query->where(fn ($builder) => $builder->where('ticket_number', 'like', '%'.$validated['q'].'%')->orWhere('subject', 'like', '%'.$validated['q'].'%'));
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $tickets = $query->paginate($validated['per_page'] ?? 20);
        $tickets->getCollection()->each(fn (Ticket $ticket) => $ticket->makeHidden(['assignee_id']));

        return response()->json(['data' => $tickets]);
    }

    public function create(Request $request)
    {
        $data = $request->validate(['request_key' => ['required', 'string', 'alpha_dash', 'min:16', 'max:64'], 'category_id' => ['nullable', 'integer', Rule::exists('helpdesk_categories', 'id')->where('is_active', true)], 'subject' => ['required', 'string', 'min:3', 'max:200'], 'body' => ['required', 'string', 'min:3', 'max:20000']]);
        $ticket = $this->tickets->create((int) $request->user()->id, $data);

        $ticket->makeHidden(['assignee_id']);

        return response()->json(['data' => $ticket], 201);
    }

    public function ownShow(Request $request, int $ticket)
    {
        $record = Ticket::query()->where('user_id', $request->user()->id)->with('category')->findOrFail($ticket);
        $messages = DB::table('helpdesk_messages as message')->leftJoin('users as author', 'author.id', '=', 'message.user_id')->where('message.ticket_id', $record->id)->where('message.is_public', true)->orderByDesc('message.id')->select('message.id', 'message.ticket_id', 'message.user_id', 'message.body', 'message.is_public', 'message.created_at', 'author.name as author_name', 'author.nickname as author_nickname')->limit(101)->get();
        $record->setAttribute('messages_truncated', $messages->count() > 100);
        $messages = $messages->take(100)->reverse()->values();
        $record->setRelation('messages', $messages);
        $record->makeHidden(['assignee_id']);

        return response()->json(['data' => $record]);
    }

    public function ownReply(Request $request, int $ticket)
    {
        $record = Ticket::query()->where('user_id', $request->user()->id)->findOrFail($ticket);
        $data = $request->validate(['request_key' => ['required', 'string', 'alpha_dash', 'min:16', 'max:64'], 'body' => ['required', 'string', 'min:1', 'max:20000']]);
        $message = $this->tickets->addMessage($record, (int) $request->user()->id, $data['body'], true, $data['request_key'], 'reply', true);

        return response()->json(['data' => $message], 201);
    }

    public function reopen(Request $request, int $ticket)
    {
        $record = Ticket::query()->where('user_id', $request->user()->id)->findOrFail($ticket);
        $updated = $this->tickets->changeStatus($record, 'open', (int) $request->user()->id, true);
        $updated->makeHidden(['assignee_id']);

        return response()->json(['data' => $updated]);
    }

    public function adminIndex(Request $request, bool $assignedOnly = false)
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'])], 'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])], 'unassigned' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $user = $request->user();
        $canReadAll = $user->hasPermission('glitter-help_desk.tickets.read-all', PermissionType::Admin);
        $query = Ticket::query()->with('category')->latest('updated_at');
        if (! $canReadAll || $assignedOnly) {
            $query->where('assignee_id', $user->id);
        }
        if (! empty($validated['unassigned']) && $canReadAll) {
            $query->whereNull('assignee_id');
        }
        if (! empty($validated['q'])) {
            $query->where(fn ($builder) => $builder->where('ticket_number', 'like', '%'.$validated['q'].'%')->orWhere('subject', 'like', '%'.$validated['q'].'%'));
        }
        foreach (['status', 'priority'] as $filter) {
            if (! empty($validated[$filter])) {
                $query->where($filter, $validated[$filter]);
            }
        }

        return response()->json(['data' => $query->paginate($validated['per_page'] ?? 25)]);
    }

    public function assignedIndex(Request $request)
    {
        return $this->adminIndex($request, true);
    }

    public function adminShow(Request $request, int $ticket)
    {
        $record = $this->visibleAdminTicket($request->user(), $ticket);
        $messages = DB::table('helpdesk_messages as message')
            ->leftJoin('users as author', 'author.id', '=', 'message.user_id')
            ->where('message.ticket_id', $record->id)
            ->when(! $request->user()->hasPermission('glitter-help_desk.notes.manage', PermissionType::Admin), fn ($query) => $query->where('message.is_public', true))
            ->orderByDesc('message.id')
            ->select('message.id', 'message.ticket_id', 'message.user_id', 'message.body', 'message.is_public', 'message.created_at', 'author.name as author_name', 'author.nickname as author_nickname')
            ->limit(101)->get();
        $record->setAttribute('messages_truncated', $messages->count() > 100);
        $messages = $messages->take(100)->reverse()->values();
        $record->setRelation('messages', $messages);
        if ($request->user()->hasPermission('glitter-help_desk.tickets.read-all', PermissionType::Admin) || $request->user()->hasPermission('glitter-help_desk.tickets.read-assigned', PermissionType::Admin)) {
            $events = DB::table('helpdesk_events as event')->leftJoin('users as actor', 'actor.id', '=', 'event.actor_id')->where('event.ticket_id', $record->id)->orderByDesc('event.id')->select('event.*', 'actor.name as actor_name', 'actor.nickname as actor_nickname')->limit(101)->get();
            $record->setAttribute('events_truncated', $events->count() > 100);
            $record->setAttribute('events', $events->take(100)->reverse()->values());
            $assignments = DB::table('helpdesk_assignments as assignment')->leftJoin('users as old_agent', 'old_agent.id', '=', 'assignment.from_user_id')->leftJoin('users as new_agent', 'new_agent.id', '=', 'assignment.to_user_id')->leftJoin('users as actor', 'actor.id', '=', 'assignment.changed_by')->where('assignment.ticket_id', $record->id)->orderByDesc('assignment.id')->select('assignment.*', 'old_agent.name as from_name', 'old_agent.nickname as from_nickname', 'new_agent.name as to_name', 'new_agent.nickname as to_nickname', 'actor.name as changed_by_name', 'actor.nickname as changed_by_nickname')->limit(101)->get();
            $record->setAttribute('assignments', $assignments->take(100)->reverse()->values());
        }

        return response()->json(['data' => $record->load(['category', 'requester:id,name,nickname'])]);
    }

    public function adminReply(Request $request, int $ticket)
    {
        $record = $this->visibleAdminTicket($request->user(), $ticket);
        $data = $request->validate(['request_key' => ['required', 'string', 'alpha_dash', 'min:16', 'max:64'], 'body' => ['required', 'string', 'min:1', 'max:20000']]);
        $message = $this->tickets->addMessage($record, (int) $request->user()->id, $data['body'], true, $data['request_key']);

        return response()->json(['data' => $message], 201);
    }

    public function note(Request $request, int $ticket)
    {
        $record = $this->visibleAdminTicket($request->user(), $ticket);
        $data = $request->validate(['request_key' => ['required', 'string', 'alpha_dash', 'min:16', 'max:64'], 'body' => ['required', 'string', 'min:1', 'max:20000']]);
        $message = $this->tickets->addMessage($record, (int) $request->user()->id, $data['body'], false, $data['request_key'], 'note');

        return response()->json(['data' => $message], 201);
    }

    public function updateStatus(Request $request, int $ticket)
    {
        $record = $this->visibleAdminTicket($request->user(), $ticket);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'])]]);

        return response()->json(['data' => $this->tickets->changeStatus($record, $data['status'], (int) $request->user()->id)]);
    }

    public function updatePriority(Request $request, int $ticket)
    {
        $record = $this->visibleAdminTicket($request->user(), $ticket);
        $data = $request->validate(['priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])]]);

        return response()->json(['data' => $this->tickets->changePriority($record, $data['priority'], (int) $request->user()->id)]);
    }

    public function assign(Request $request, int $ticket)
    {
        $record = $this->visibleAdminTicket($request->user(), $ticket);
        $data = $request->validate(['assignee_id' => ['present', 'nullable', 'integer', Rule::exists('users', 'id')]]);
        if ($data['assignee_id'] !== null) {
            $assignee = User::query()->findOrFail($data['assignee_id']);
            abort_unless($assignee->hasPermission('glitter-help_desk.tickets.read-assigned', PermissionType::Admin), 422, __('glitter-help_desk::messages.agent_invalid'));
        }

        return response()->json(['data' => $this->tickets->assign($record, $data['assignee_id'], (int) $request->user()->id)]);
    }

    public function agents()
    {
        $agents = User::query()->where('status', 'active')->whereHas('roles', fn ($role) => $role->where('is_active', true)->whereHas('permissions', fn ($permission) => $permission->where('identifier', 'glitter-help_desk.tickets.read-assigned')->where('type', 'admin')))->orderBy('name')->limit(100)->get(['id', 'name', 'nickname']);

        return response()->json(['data' => $agents]);
    }

    public function categoriesAdmin()
    {
        return response()->json(['data' => Category::query()->with('parent')->orderBy('sort_order')->orderBy('name')->paginate(50)]);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'parent_id' => ['nullable', 'integer', Rule::exists('helpdesk_categories', 'id')], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'], 'is_active' => ['sometimes', 'boolean']]);
        $category = DB::transaction(function () use ($data) {
            Category::query()->lockForUpdate()->get(['id']);
            $this->assertCategoryParent(null, $data['parent_id'] ?? null);
            return Category::query()->create($data);
        });
        return response()->json(['data' => $category], 201);
    }

    public function updateCategory(Request $request, int $category)
    {
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:120'], 'parent_id' => ['nullable', 'integer', Rule::exists('helpdesk_categories', 'id')], 'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'], 'is_active' => ['sometimes', 'boolean']]);
        $record = DB::transaction(function () use ($category, $data) {
            Category::query()->lockForUpdate()->get(['id']);
            $record = Category::query()->findOrFail($category);
            if (array_key_exists('parent_id', $data)) $this->assertCategoryParent($record->id, $data['parent_id']);
            $record->fill($data)->save();
            return $record;
        });

        return response()->json(['data' => $record]);
    }

    public function cannedReplies(Request $request)
    {
        $query = CannedReply::query()->orderBy('title');
        if ($request->boolean('active_only')) $query->where('is_active', true);
        return response()->json(['data' => $query->paginate(50)]);
    }

    public function storeCannedReply(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:120'], 'body' => ['required', 'string', 'max:20000'], 'is_active' => ['sometimes', 'boolean']]);
        $data['created_by'] = $request->user()->id;

        return response()->json(['data' => CannedReply::query()->create($data)], 201);
    }

    public function updateCannedReply(Request $request, int $reply)
    {
        $record = CannedReply::query()->findOrFail($reply);
        $data = $request->validate(['title' => ['sometimes', 'required', 'string', 'max:120'], 'body' => ['sometimes', 'required', 'string', 'max:20000'], 'is_active' => ['sometimes', 'boolean']]);
        $record->fill($data)->save();

        return response()->json(['data' => $record]);
    }

    public function settings()
    {
        return response()->json(['data' => DB::table('helpdesk_settings')->orderBy('setting_key')->get()]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate(['settings' => ['sometimes', 'array'], 'settings.*' => ['nullable'], 'allow_customer_reopen' => ['sometimes', 'boolean'], 'customer_reply_status' => ['sometimes', Rule::in(['open', 'waiting_customer'])], 'default_priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])]]);
        $settings = $data['settings'] ?? array_intersect_key($data, array_flip(['allow_customer_reopen', 'customer_reply_status', 'default_priority']));
        abort_if($settings === [], 422, __('glitter-help_desk::messages.setting_required'));
        foreach ($settings as $key => $value) {
            abort_unless(in_array($key, ['allow_customer_reopen'], true), 422, __('glitter-help_desk::messages.setting_invalid'));
            $valid = match ($key) {
                'allow_customer_reopen' => is_bool($value),
            };
            abort_unless($valid, 422, __('glitter-help_desk::messages.setting_invalid'));
            DB::table('helpdesk_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        }

        return $this->settings();
    }

    public function report()
    {
        $counts = Ticket::query()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        $recent = Ticket::query()->where('created_at', '>=', now()->subDays(30))->count();
        $unassigned = Ticket::query()->whereNull('assignee_id')->whereNotIn('status', ['closed', 'resolved'])->count();

        return response()->json(['data' => ['by_status' => $counts, 'created_last_30_days' => $recent, 'unassigned_open' => $unassigned]]);
    }

    public function destroyCannedReply(int $reply)
    {
        CannedReply::query()->findOrFail($reply)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function visibleAdminTicket(User $user, int $ticketId): Ticket
    {
        $query = Ticket::query();
        if (! $user->hasPermission('glitter-help_desk.tickets.read-all', PermissionType::Admin)) {
            $query->where('assignee_id', $user->id);
        }

        return $query->findOrFail($ticketId);
    }

    private function assertCategoryParent(?int $categoryId, ?int $parentId): void
    {
        $visited = $categoryId === null ? [] : [$categoryId => true];
        while ($parentId !== null) {
            abort_if(isset($visited[$parentId]), 422, __('glitter-help_desk::messages.category_cycle'));
            $visited[$parentId] = true;
            $parentId = Category::query()->whereKey($parentId)->value('parent_id');
            $parentId = $parentId === null ? null : (int) $parentId;
        }
    }
}
