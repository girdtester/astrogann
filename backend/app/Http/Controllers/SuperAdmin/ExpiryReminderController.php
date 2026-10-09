<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ExpiryReminder;
use App\Services\ExpiryReminderService;
use App\Support\Sorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;
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
            'health' => $this->health(),
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

    /** Live check (D-97): is the cron running, is anything stuck, which mail server is used. */
    private function health(): array
    {
        $last = Cache::get('astrogann.cron_last_run');

        return [
            'cron_last_run' => $last ? Carbon::parse($last) : null,
            'cron_ok' => $last && Carbon::parse($last)->gt(now()->subMinutes(10)),
            'queued' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'mailer' => config('mail.default'),
            'mail_host' => config('mail.mailers.'.config('mail.default').'.host'),
            'mail_from' => config('mail.from.address'),
        ];
    }

    /** Send one test e-mail right now (not queued) and show the exact error if it fails (D-97). */
    public function testMail(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);

        try {
            Mail::raw(
                'This is a test e-mail from '.config('app.name').' ('.config('app.url').') sent at '.now()->format('d M Y, h:i A')
                .".\n\nIf you can read this, e-mail sending works.",
                fn ($m) => $m->to($data['email'])->subject(config('app.name').' — test e-mail')
            );
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Test e-mail failed: '.Str::limit($e->getMessage(), 300));
        }

        return back()->with('success', "Test e-mail sent to {$data['email']}. Check the inbox (and Spam).");
    }

    public function retry(): RedirectResponse
    {
        return back()->with('success', $this->reminders->retryFailed().' failed reminder(s) put back in the queue.');
    }
}
