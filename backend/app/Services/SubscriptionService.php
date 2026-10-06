<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\RoleKind;
use App\Enums\SubscriptionStatus;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Subscription and access logic (spec 3.4, DECISIONS D-17, D-18, D-20, D-22).
 *
 * One global subscription per user. Every access check goes through isActiveFor() and every
 * "who gets the push" query through eligibleUsersFor(), so per-category subscriptions can be added
 * later by changing only these methods.
 */
class SubscriptionService
{
    public function __construct(
        private readonly ActivityLogService $logs,
        private readonly SettingsService $settings,
    ) {}

    /** The user's latest subscription row (history model, D-22). */
    public function current(User $user): ?Subscription
    {
        return $user->subscriptions()->latest('id')->first();
    }

    public function isActiveFor(User $user, ?Category $category = null): bool
    {
        return (bool) $this->current($user)?->isCurrentlyActive();
    }

    /** Number of entries the user may see in a category: 7 when active, 3 otherwise (from settings). */
    public function entryLimitFor(User $user, ?Category $category = null): int
    {
        return $this->isActiveFor($user, $category)
            ? $this->settings->entriesLimitActive()
            : $this->settings->entriesLimitExpired();
    }

    /** Active traders who should receive new-entry notifications for the category (D-17, D-18). */
    public function eligibleUsersFor(Category $category): Builder
    {
        return User::query()
            ->ofKind(RoleKind::User)
            ->where('status', 'active')
            ->whereHas('subscriptions', fn (Builder $q) => $q->currentlyActive());
    }

    /** All active trader accounts (any subscription state). */
    public function notifiableTraders(): Builder
    {
        return User::query()->ofKind(RoleKind::User)->where('status', 'active');
    }

    /**
     * Which of these user ids currently have an active subscription (one query).
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function filterActive(array $userIds, ?Category $category = null): array
    {
        return Subscription::query()->currentlyActive()->whereIn('user_id', $userIds)
            ->distinct()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Status summary for screens and the API. */
    public function summary(User $user): array
    {
        $sub = $this->current($user);
        $active = (bool) $sub?->isCurrentlyActive();

        return [
            'status' => $sub === null ? 'none' : ($active ? 'active' : 'expired'),
            'is_active' => $active,
            'start_date' => $sub?->start_date?->toDateString(),
            'end_date' => $sub?->end_date?->toDateString(),
            'days_left' => $active ? $sub->daysLeft() : 0,
            'entries_limit' => $active ? $this->settings->entriesLimitActive() : $this->settings->entriesLimitExpired(),
        ];
    }

    /**
     * Start a new subscription period; any active one is closed first.
     *
     * @param  array{amount?: mixed, payment_date?: mixed, transaction_ref?: ?string, status?: ?string}|null  $payment
     */
    public function activate(User $user, CarbonInterface|string $start, CarbonInterface|string $end, User $by, ?string $notes = null, ?array $payment = null): Subscription
    {
        $this->assertTrader($user);
        [$start, $end] = [Carbon::parse($start)->startOfDay(), Carbon::parse($end)->startOfDay()];
        if ($end->lt($start)) {
            throw new InvalidArgumentException('End date must be on or after the start date.');
        }

        return DB::transaction(function () use ($user, $start, $end, $by, $notes, $payment) {
            $user->subscriptions()->where('status', SubscriptionStatus::Active->value)
                ->update(['status' => SubscriptionStatus::Expired->value]);

            $sub = $user->subscriptions()->create([
                'start_date' => $start, 'end_date' => $end, 'status' => SubscriptionStatus::Active,
                'created_by' => $by->id, 'notes' => $notes,
            ]);

            $this->maybeRecordPayment($user, $sub, $by, $payment);
            $this->logs->log('subscription.activated', $sub,
                "Activated for {$user->email}: {$start->toDateString()} → {$end->toDateString()}", $by);

            return $sub;
        });
    }

    /** Move the end date of the current period forward (re-activates it if it had expired). */
    public function extend(User $user, CarbonInterface|string $newEnd, User $by, ?array $payment = null): Subscription
    {
        $this->assertTrader($user);
        $sub = $this->current($user) ?? throw new InvalidArgumentException('This user has no subscription yet. Activate one first.');
        $newEnd = Carbon::parse($newEnd)->startOfDay();

        if ($newEnd->lte($sub->end_date) || $newEnd->lt(today())) {
            throw new InvalidArgumentException('The new end date must be after the current end date and not in the past.');
        }

        return DB::transaction(function () use ($user, $sub, $newEnd, $by, $payment) {
            $old = $sub->end_date->toDateString();
            $sub->update(['end_date' => $newEnd, 'status' => SubscriptionStatus::Active, 'reminder_sent_at' => null]);

            $this->maybeRecordPayment($user, $sub, $by, $payment);
            $this->logs->log('subscription.extended', $sub, "Extended for {$user->email}: {$old} → {$newEnd->toDateString()}", $by);

            return $sub;
        });
    }

    /** End access now. */
    public function expire(User $user, User $by): Subscription
    {
        $this->assertTrader($user);
        $sub = $this->current($user) ?? throw new InvalidArgumentException('This user has no subscription.');

        $sub->update([
            'status' => SubscriptionStatus::Expired,
            'end_date' => $sub->end_date->gt(today()) ? today() : $sub->end_date,
        ]);
        $this->logs->log('subscription.expired', $sub, "Expired manually for {$user->email}", $by);

        return $sub;
    }

    /** Record a manual payment (D-20). */
    public function recordPayment(User $user, array $data, User $by, ?Subscription $sub = null): SubscriptionPayment
    {
        $this->assertTrader($user);

        $payment = $user->payments()->create([
            'subscription_id' => $sub?->id ?? $this->current($user)?->id,
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'] ?? today(),
            'transaction_ref' => $data['transaction_ref'] ?? null,
            'status' => $data['status'] ?? PaymentStatus::Paid->value,
            'recorded_by' => $by->id,
        ]);
        $this->logs->log('payment.recorded', $payment, "Payment {$payment->amount} for {$user->email}", $by);

        return $payment;
    }

    /** Daily job: mark subscriptions past their end date as expired. Returns the number updated. */
    public function expireOverdue(): int
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('end_date', '<', today()->toDateString())
            ->update(['status' => SubscriptionStatus::Expired->value, 'updated_at' => now()]);
    }

    /**
     * Active subscriptions ending within the next N days (today .. today+N) that have not been reminded yet.
     * A window (not an exact day) means a missed cron day or a short new period still gets one reminder.
     */
    public function dueForReminder(?int $days = null): Builder
    {
        $days ??= $this->settings->reminderDays();

        return Subscription::query()
            ->with('user')
            ->where('status', SubscriptionStatus::Active->value)
            ->where('end_date', '>=', today()->toDateString())
            ->where('end_date', '<=', today()->addDays($days)->toDateString().' 23:59:59')
            ->whereNull('reminder_sent_at')
            ->whereHas('user', fn (Builder $q) => $q->where('status', 'active')->ofKind(RoleKind::User));
    }

    private function maybeRecordPayment(User $user, Subscription $sub, User $by, ?array $payment): void
    {
        if ($payment && filled($payment['amount'] ?? null)) {
            $this->recordPayment($user, $payment, $by, $sub);
        }
    }

    private function assertTrader(User $user): void
    {
        if (! $user->isTrader()) {
            throw new InvalidArgumentException('Subscriptions apply to users/traders only.');
        }
    }
}
