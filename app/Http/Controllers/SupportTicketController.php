<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SupportTicketController extends Controller
{
    private const array MESSAGE_RULES = ['body' => ['required', 'string', 'max:4000']];

    public function index(Request $request): JsonResponse
    {
        $status = $request->validate(['status' => ['sometimes', 'required', 'in:open,closed']])['status'] ?? null;
        $admin = $this->isAdminInbox($request);
        $tickets = $this->tickets($request);
        if ($status === 'open') {
            $tickets->whereNull('closed_at');
        } elseif ($status === 'closed') {
            $tickets->whereNotNull('closed_at');
        }

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

        return $this->respond($this->details($record, $this->isAdminInbox($request)));
    }

    public function reply(Request $request, string $ticket): JsonResponse
    {
        $admin = $this->isAdminInbox($request);

        $record = DB::transaction(function () use ($request, $ticket, $admin): SupportTicket {
            $record = $this->tickets($request)->lockForUpdate()->findOrFail($this->validId($ticket));
            abort_if($record->closed_at !== null, 409, 'Bu destek talebi kapalı. Güncel durumu görmek için talebi yenileyin.');

            $fields = $request->validate(self::MESSAGE_RULES);
            $this->writeMessage($record, $request->user(), $fields['body'], $admin);
            $record->touch();

            if ($admin) {
                $record->requester->notify(new SupportTicketNotification($record->id,
                    'Destek talebinize yanıt geldi', 'Yanıtı destek talebinizden okuyabilirsiniz.'));
            } else {
                $this->notifyAdministrators($record, 'Destek talebine yeni mesaj', 'Açık bir destek talebine yeni mesaj geldi.');
            }

            return $record;
        }, attempts: 3);

        return $this->respond($this->details($record, $admin), 201);
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

        return $this->respond($this->details($record, true));
    }

    private function isAdminInbox(Request $request): bool
    {
        return $request->routeIs('admin.support-tickets.*') && $request->user()->is_admin;
    }

    /** @return Builder<SupportTicket> */
    private function tickets(Request $request): Builder
    {
        return $this->isAdminInbox($request)
            ? SupportTicket::query()->with('requester:id,name')
            : SupportTicket::query()->whereBelongsTo($request->user(), 'requester');
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
        $administrators = User::query()->where('is_admin', true)->whereNotNull('email_verified_at')->get();
        Notification::send($administrators, new SupportTicketNotification($ticket->id, $title, $message));
    }

    private function summary(SupportTicket $ticket, bool $admin): array
    {
        $data = $ticket->only(['id', 'subject', 'updated_at']) + [
            'status' => $ticket->closed_at === null ? 'open' : 'closed',
        ];
        if ($admin) {
            $data['requester_name'] = $ticket->requester->name;
        }

        return $data;
    }

    private function details(SupportTicket $ticket, bool $admin): array
    {
        return $this->summary($ticket, $admin) + [
            'messages' => $ticket->messages()->latest('id')
                ->paginate(20, ['id', 'body', 'from_support', 'created_at'], 'message_page')->toArray(),
        ];
    }

    private function respond(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status)
            ->header('Cache-Control', 'private, no-store');
    }
}
