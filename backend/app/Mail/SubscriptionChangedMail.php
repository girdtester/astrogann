<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\Fmt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when the Super Admin activates, extends or expires a subscription (D-81), one by one via SendMail (D-87).
 */
class SubscriptionChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param 'activated'|'extended'|'expired' $action */
    public function __construct(public readonly User $user, public readonly Subscription $subscription, public readonly string $action) {}

    public function envelope(): Envelope
    {
        $app = app(SettingsService::class)->get('app_name');
        $replyTo = app(SettingsService::class)->get('contact_email');

        return new Envelope(
            subject: "Your {$app} subscription has been {$this->action}",
            replyTo: $replyTo ? [new Address($replyTo, $app)] : [],
        );
    }

    public function content(): Content
    {
        $s = app(SettingsService::class)->public();

        return new Content(markdown: 'mail.subscription-changed', with: [
            'name' => $this->user->name,
            'action' => $this->action,
            'startDate' => Fmt::date($this->subscription->start_date),
            'endDate' => Fmt::date($this->subscription->end_date),
            'appName' => $s['app_name'],
            'contactPhone' => $s['contact_phone'],
            'contactEmail' => $s['contact_email'],
            'url' => route('user.dashboard'),
        ]);
    }

    /** Short text for the in-app notification. */
    public static function summary(Subscription $sub, string $action): string
    {
        return match ($action) {
            'activated' => 'Your subscription is active until '.Fmt::date($sub->end_date).'. Enjoy full access!',
            'extended' => 'Your subscription has been extended until '.Fmt::date($sub->end_date).'.',
            default => 'Your subscription has expired. Contact the admin to renew.',
        };
    }
}
