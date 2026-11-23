<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupportTicketController extends Controller
{
    private const array MESSAGE_RULES = ['body' => ['required', 'string', 'max:4000']];

    public function index(Request $request): JsonResponse
    {
        $admin = $this->isPlatformInbox($request);
        $statuses = $admin ? ['open', 'closed', 'awaiting_reply'] : ['open', 'closed'];
        $filters = $request->validate(['status' => ['sometimes', 'required', Rule::in($statuses)]]
            + ($admin ? [
                'requester_id' => ['sometimes', 'required', 'integer', 'min:1'],
                'search' => ['nullable', 'string', 'max:120'],
                'assignment' => ['sometimes', 'required', 'in:mine,unassigned'],
            ] : []));
        $tickets = $this->tickets($request);
        if (isset($filters['requester_id'])) {
            $tickets->where('requester_id', $filters['requester_id']);
        }
        if ($admin) {
            $tickets->withLastMessageDirection()
                ->withMin(['messages as first_support_reply_at' => fn (Builder $messages) => $messages->where('from_support', true)], 'created_at');
        }
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $id = filter_var(ltrim($search, '#'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                $tickets->whereLike('subject', '%'.$search.'%');
            } else {
                $tickets->whereKey($id);
            }
        }
        match ($filters['status'] ?? null) {
            'open' => $tickets->whereNull('closed_at'),
            'closed' => $tickets->whereNotNull('closed_at'),
            'awaiting_reply' => $tickets->awaitingSupportReply(),
            default => null,
        };
        match ($filters['assignment'] ?? null) {
            'mine' => $tickets->whereHas('assignment', fn (Builder $assignment) => $assignment
                ->where('assigned_to_user_id', $request->user()->id)),
            'unassigned' => $tickets->whereDoesntHave('assignment.assignee', fn (Builder $assignee) => $assignee->verifiedSupportOperator()),
            default => null,
        };

        $page = $tickets->latest('updated_at')->latest('id')->paginate(20)
            ->through(fn (SupportTicket $item): array => $this->summary($item, $admin));

        return $this->respond($page->toArray());
    }

    public function store(Request $request): JsonResponse
    {
        $fields = $request->validate(['subject' => ['required', 'string', 'max:120']] + self::MESSAGE_RULES);
        $requester = $request->user();

        $ticket = DB::transaction(function () use ($requester, $fields): SupportTicket {
            $ticket = $requester->supportTickets()->create(['subject' => $fields['subject']]);
            $this->writeMessage($ticket, $requester, $fields['body'], false);
            $this->notifyAdministrators($ticket, 'Yeni destek talebi', 'Yeni bir destek talebi açıldı.');

            return $ticket;
        }, attempts: 3);

        return $this->respond($this->details($ticket, false), 201);
    }

    public function show(Request $request, string $ticket): JsonResponse
    {
        $record = $this->tickets($request)->findOrFail($this->validId($ticket));

        return $this->respond($this->details($record, $this->isPlatformInbox($request), $request->user()->isVerifiedPlatformAdmin()));
    }

    public function reply(Request $request, string $ticket): JsonResponse
    {
        $admin = $this->isPlatformInbox($request);
        $actor = $request->user();

        $record = DB::transaction(function () use ($request, $ticket, $admin, $actor): SupportTicket {
            $record = $this->tickets($request)->lockForUpdate()->findOrFail($this->validId($ticket));
            if ($admin && ! $actor->isVerifiedPlatformAdmin()) {
                $operator = User::query()->lockForUpdate()->findOrFail($actor->id);
                abort_unless($operator->isVerifiedPlatformSupport()
                    && (int) $record->assignment()->value('assigned_to_user_id') === $actor->id, 404);
            }
            abort_if($record->closed_at !== null, 409, 'Bu destek talebi kapalı. Güncel durumu görmek için talebi yenileyin.');

            $fields = $request->validate(self::MESSAGE_RULES);
            $this->writeMessage($record, $actor, $fields['body'], $admin);
            $record->touch();

            if ($admin) {
                $record->requester->notify(new SupportTicketNotification($record->id,
                    'Destek talebinize yanıt geldi', 'Yanıtı destek talebinizden okuyabilirsiniz.'));
            } else {
                $this->notifyAdministrators($record, 'Destek talebine yeni mesaj', 'Açık bir destek talebine yeni mesaj geldi.');
                $assignee = $record->assignment?->assignee;
                if ($assignee?->isVerifiedPlatformSupport() && ! $assignee->isVerifiedPlatformAdmin()) {
                    $assignee->notify(new SupportTicketNotification($record->id,
                        'Destek talebine yeni mesaj', 'Size atanan bir destek talebine yeni mesaj geldi.'));
                }
            }

            return $record;
        }, attempts: 3);

        return $this->respond($this->details($record, $admin, $actor->isVerifiedPlatformAdmin()), 201);
    }

    public function updateStatus(Request $request, string $ticket): JsonResponse
    {
        $record = DB::transaction(function () use ($request, $ticket): SupportTicket {
            $record = $this->tickets($request)->lockForUpdate()->findOrFail($this->validId($ticket));
            $closing = $request->validate(['status' => ['required', 'in:open,closed']])['status'] === 'closed';
            if ($closing !== ($record->closed_at !== null)) {
                $record->closed_at = $closing ? now() : null;
                $record->save();
            }

            return $record;
        }, attempts: 3);

        return $this->respond($this->details($record, true, true));
    }

    public function updateAssignment(Request $request, string $ticket): JsonResponse
    {
        $fields = $request->validate([
            'assigned_to_user_id' => ['present', 'nullable', 'integer', 'min:1'],
            'assignment_id' => ['present', 'nullable', 'integer', 'min:1'],
        ]);
        $assigneeId = isset($fields['assigned_to_user_id']) ? (int) $fields['assigned_to_user_id'] : null;
        $expected = isset($fields['assignment_id']) ? (int) $fields['assignment_id'] : null;
        $action = $assigneeId === null ? 'unassigned' : 'assigned';
        $actor = $request->user();

        $record = DB::transaction(function () use ($request, $ticket, $assigneeId, $expected, $action, $actor): SupportTicket {
            $record = $this->tickets($request)->lockForUpdate()->findOrFail($this->validId($ticket));
            $current = $record->assignment;
            if ($current?->assigned_to_user_id === $assigneeId && ($current === null || $current->action === $action)) {
                return $record;
            }

            abort_unless($current?->id === $expected, 409, 'Sorumlu başka bir oturumda değişti. Talebi yenileyin.');
            abort_if($record->closed_at !== null, 409, 'Sorumluyu değiştirmek için önce talebi yeniden açın.');
            $assignee = $assigneeId === null ? null : User::query()->verifiedSupportOperator()->lockForUpdate()->find($assigneeId);
            if ($assigneeId !== null && $assignee === null) {
                throw ValidationException::withMessages(['assigned_to_user_id' => 'Doğrulanmış bir platform yöneticisi veya destek görevlisi seçin.']);
            }
            $assignment = $record->assignments()->create([
                'assigned_to_user_id' => $assigneeId, 'assigned_by_user_id' => $actor->id, 'action' => $action,
            ]);
            $record->setRelation('assignment', $assignment->setRelation('assignee', $assignee));
            if ($assignee !== null && $assignee->id !== $actor->id) {
                $assignee->notify(new SupportTicketNotification($record->id,
                    'Destek talebi size atandı', 'Atanan talebi destek kuyruğundan açabilirsiniz.'));
            }

            return $record;
        }, attempts: 3);

        return $this->respond($this->details($record, true, true));
    }

    private function isPlatformInbox(Request $request): bool
    {
        return $request->routeIs('admin.support-tickets.*');
    }

    /** @return Builder<SupportTicket> */
    private function tickets(Request $request): Builder
    {
        $user = $request->user();
        if (! $this->isPlatformInbox($request)) {
            return SupportTicket::query()->whereBelongsTo($user, 'requester');
        }

        return SupportTicket::query()->with(['requester:id,name', 'assignment.assignee:id,name,is_admin,email_verified_at,platform_role'])
            ->when(! $user->isVerifiedPlatformAdmin(), fn (Builder $query) => $query->whereHas('assignment',
                fn (Builder $assignment) => $assignment->where('assigned_to_user_id', $user->id)));
    }

    private function validId(string $ticket): int
    {
        $id = filter_var($ticket, FILTER_VALIDATE_INT);
        abort_unless($id !== false && $id > 0, 404);

        return $id;
    }

    private function writeMessage(SupportTicket $ticket, User $sender, string $body, bool $fromSupport): void
    {
        $ticket->messages()->create([
            'sender_user_id' => $sender->id,
            'body' => $body,
            'from_support' => $fromSupport,
        ]);
    }

    private function notifyAdministrators(SupportTicket $ticket, string $title, string $message): void
    {
        $administrators = User::query()->verifiedPlatformAdmin()->get();
        Notification::send($administrators, new SupportTicketNotification($ticket->id, $title, $message));
    }

    private function summary(SupportTicket $ticket, bool $admin): array
    {
        $data = $ticket->only(['id', 'subject', 'updated_at']) + [
            'status' => $ticket->closed_at === null ? 'open' : 'closed',
        ];
        if ($admin) {
            $data['requester_name'] = $ticket->requester->name;
            $assignment = $ticket->assignment;
            $assignee = $assignment?->assignee;
            $data['assignment_id'] = $assignment?->id;
            $data['assignee'] = $assignee?->canUsePlatformSupport() ? $assignee->only(['id', 'name']) : null;
            $data['assignment_needs_attention'] = $assignment?->action === 'assigned' && $data['assignee'] === null;
            if (array_key_exists('last_message_from_support', $ticket->getAttributes())) {
                $data['awaiting_reply'] = $ticket->closed_at === null && $ticket->last_message_from_support === false;
            }
            if (array_key_exists('first_support_reply_at', $ticket->getAttributes())) {
                $firstReply = $ticket->first_support_reply_at;
                $data['first_response_seconds'] = $ticket->created_at && $firstReply
                    ? (int) $ticket->created_at->diffInSeconds(CarbonImmutable::parse($firstReply)) : null;
                $data['resolution_seconds'] = $ticket->created_at && $ticket->closed_at
                    ? (int) $ticket->created_at->diffInSeconds($ticket->closed_at) : null;
            }
        }

        return $data;
    }

    private function details(SupportTicket $ticket, bool $admin, bool $canAssign = false): array
    {
        $data = $this->summary($ticket, $admin) + [
            'messages' => $ticket->messages()->latest('id')
                ->paginate(20, ['id', 'body', 'from_support', 'created_at'], 'message_page')->toArray(),
        ];
        if ($admin && $canAssign) {
            $data['assignment_candidates'] = User::query()->verifiedSupportOperator()->orderBy('name')->orderBy('id')->get(['id', 'name']);
        }

        return $data;
    }

    private function respond(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status)
            ->header('Cache-Control', 'private, no-store');
    }
}
