<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ExpiryReminder;
use App\Services\ExpiryReminderService;
use App\Support\Sorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The saved expiry-reminder list (D-88): who will get / got the "expires in N days" e-mail and when.
 */
class ExpiryReminderController extends Controller
{
    public function __construct(private readonly ExpiryReminderService $reminders) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,sent,failed'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = ExpiryReminder::query()
            ->with('user:id,name,email,role_id')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->whereIn('user_id', \App\Models\User::query()->select('id')
                ->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%"))));

        $sorting = Sorting::apply($query, $request, ['collected' => 'created_at', 'end' => 'end_date', 'sent' => 'sent_at'], 'collected');

        return view('superadmin.reminders.index', [
            'reminders' => $query->paginate(Sorting::perPage($request, 50))->onEachSide(1)->withQueryString(),
            'sorting' => $sorting,
            'filters' => $filters,
            'counts' => ExpiryReminder::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function collect(): RedirectResponse
    {
        $n = $this->reminders->collect();

        return back()->with('success', "Saved {$n} user(s) whose subscription expires soon. They will be e-mailed one by one by the cron.");
    }

    public function send(): RedirectResponse
    {
        $r = $this->reminders->sendPending();

        return back()->with('success', "Sent {$r['sent']} e-mail(s) now".($r['failed'] ? ", {$r['failed']} failed (see the list)" : '').'. The cron sends the rest automatically.');
    }

    public function retry(): RedirectResponse
    {
        return back()->with('success', $this->reminders->retryFailed().' failed reminder(s) put back in the queue.');
    }
}
