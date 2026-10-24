<?php

namespace Modules\Glitter\HelpDesk\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Modules\Glitter\HelpDesk\Models\Message;
use Modules\Glitter\HelpDesk\Models\Ticket;

class TicketService
{
    private const STATUSES = ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'];

    public function create(int $userId, array $data): Ticket
    {
        try {
            return DB::transaction(function () use ($userId, $data) {
                $existing = Ticket::query()->where('user_id', $userId)->where('create_request_key', $data['request_key'])->first();
                if ($existing) {
                    return $existing->load('messages');
                }
                $ticket = Ticket::create([
                    'ticket_number' => null,
                    'user_id' => $userId,
                    'create_request_key' => $data['request_key'],
                    'category_id' => $data['category_id'] ?? null,
                    'subject' => trim(strip_tags($data['subject'])),
                    'status' => 'open',
                    'priority' => 'normal',
                ]);
                $ticket->ticket_number = 'HD-'.now()->format('Ymd').'-'.str_pad((string) $ticket->id, 8, '0', STR_PAD_LEFT);
                $ticket->save();
                $this->event($ticket, $userId, 'ticket.created', null, ['status' => 'open']);
                $this->addMessage($ticket, $userId, $data['body'], true, $data['request_key'], 'create', true);

                return $ticket->load('messages');
            });
        } catch (QueryException $exception) {
            $existing = Ticket::query()->where('user_id', $userId)->where('create_request_key', $data['request_key'])->first();
            if ($existing) {
                return $existing->load('messages');
            }
            throw $exception;
        }
    }

    public function addMessage(Ticket $ticket, int $userId, string $body, bool $public, string $requestKey, string $requestKind = 'reply', bool $customer = false): Message
    {
        return DB::transaction(function () use ($ticket, $userId, $body, $public, $requestKey, $requestKind, $customer) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $existing = $locked->messages()->where('user_id', $userId)->where('request_kind', $requestKind)->where('request_key', $requestKey)->first();
            if ($existing) {
                return $existing;
            }
            if (! $this->isMessageAllowedForStatus($locked->status)) {
                throw ValidationException::withMessages(['status' => [__('glitter-help_desk::messages.closed_ticket_message')]]);
            }
            if ($public && $customer && $userId === (int) $locked->user_id && $this->isCustomerReopenTransitionAllowed($locked->status, 'open')) {
                $this->assertCustomerReopenAllowed();
                $locked->status = 'open';
                $locked->resolved_at = null;
                $locked->save();
                $this->event($locked, $userId, 'ticket.status_changed', ['status' => 'resolved'], ['status' => 'open']);
            }
            $message = $locked->messages()->create(['user_id' => $userId, 'request_kind' => $requestKind, 'request_key' => $requestKey, 'body' => trim(strip_tags($body)), 'is_public' => $public, 'created_at' => now()]);
            if ($public && $userId !== (int) $locked->user_id && $locked->first_response_at === null) {
                $locked->first_response_at = now();
                $locked->save();
            }

            return $message;
        });
    }

    public function changeStatus(Ticket $ticket, string $status, int $actorId, bool $customer = false): Ticket
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => [__('glitter-help_desk::messages.status_invalid')]]);
        }
        if ($customer) {
            $this->assertCustomerReopenAllowed();
        }

        return DB::transaction(function () use ($ticket, $status, $actorId, $customer) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $from = $locked->status;
            $allowed = $customer
                ? $this->isCustomerReopenTransitionAllowed($from, $status)
                : $this->isStaffTransitionAllowed($from, $status);
            if (! $allowed) {
                throw ValidationException::withMessages(['status' => [__('glitter-help_desk::messages.status_transition_invalid')]]);
            }
            $locked->status = $status;
            $locked->resolved_at = $status === 'resolved' ? now() : null;
            $locked->closed_at = $status === 'closed' ? now() : null;
            $locked->save();
            $this->event($locked, $actorId, 'ticket.status_changed', ['status' => $from], ['status' => $status]);

            return $locked;
        });
    }

    public function changePriority(Ticket $ticket, string $priority, int $actorId): Ticket
    {
        if (! in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            throw ValidationException::withMessages(['priority' => [__('glitter-help_desk::messages.priority_invalid')]]);
        }

        return DB::transaction(function () use ($ticket, $priority, $actorId) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $old = $locked->priority;
            if ($old === $priority) {
                return $locked;
            }
            $locked->priority = $priority;
            $locked->save();
            $this->event($locked, $actorId, 'ticket.priority_changed', ['priority' => $old], ['priority' => $priority]);

            return $locked;
        });
    }

    public function assign(Ticket $ticket, ?int $assigneeId, int $actorId): Ticket
    {
        return DB::transaction(function () use ($ticket, $assigneeId, $actorId) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $old = $locked->assignee_id;
            if ((int) $old !== (int) $assigneeId) {
                DB::table('helpdesk_assignments')->insert(['ticket_id' => $locked->id, 'from_user_id' => $old, 'to_user_id' => $assigneeId, 'changed_by' => $actorId, 'created_at' => now()]);
                $locked->assignee_id = $assigneeId;
                $locked->save();
                $this->event($locked, $actorId, 'ticket.assigned', ['assignee_id' => $old], ['assignee_id' => $assigneeId]);
            }

            return $locked;
        });
    }

    /**
     * DB 저장 없이 관리자 상태 전이 가능 여부를 판정한다.
     */
    public function isStaffTransitionAllowed(string $from, string $to): bool
    {
        $transitions = [
            'open' => ['in_progress', 'waiting_customer', 'resolved', 'closed'],
            'in_progress' => ['waiting_customer', 'resolved', 'closed'],
            'waiting_customer' => ['in_progress', 'resolved', 'closed'],
            'resolved' => ['in_progress', 'closed'],
            'closed' => [],
        ];

        return in_array($to, $transitions[$from] ?? [], true);
    }

    /**
     * DB 저장 없이 고객 재열기 설정값의 허용 여부를 판정한다.
     * JSON boolean true만 허용하며 문자열·숫자·잘못된 JSON은 거부한다.
     */
    public function isCustomerReopenSettingAllowed(?string $settingValue): bool
    {
        return $settingValue !== null && json_decode($settingValue, true) === true;
    }

    /**
     * DB 저장 없이 고객 재열기 상태 전이를 판정한다.
     */
    public function isCustomerReopenTransitionAllowed(string $from, string $to): bool
    {
        return $from === 'resolved' && $to === 'open';
    }

    /**
     * DB 저장 없이 메시지 작성 가능 여부를 판정한다.
     */
    public function isMessageAllowedForStatus(string $status): bool
    {
        return $status !== 'closed';
    }

    private function event(Ticket $ticket, int $actorId, string $type, ?array $old, ?array $new): void
    {
        DB::table('helpdesk_events')->insert(['ticket_id' => $ticket->id, 'actor_id' => $actorId, 'event_type' => $type, 'old_value' => $old ? json_encode($old) : null, 'new_value' => $new ? json_encode($new) : null, 'created_at' => now()]);
    }

    private function assertCustomerReopenAllowed(): void
    {
        $setting = DB::table('helpdesk_settings')->where('setting_key', 'allow_customer_reopen')->value('setting_value');

        abort_unless($this->isCustomerReopenSettingAllowed($setting), 403);
    }
}
