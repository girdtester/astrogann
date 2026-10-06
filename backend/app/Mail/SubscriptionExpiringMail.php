<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Subscription will expire in N days" (spec 3.4). Queued; sent through SMTP in production.
 */
class SubscriptionExpiringMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 600];

    public function __construct(
        public readonly User $user,
        public readonly Subscription $subscription,
        public readonly int $daysLeft,
    ) {}

    public function envelope(): Envelope
    {
        $app = app(SettingsService::class)->get('app_name');
        $when = $this->daysLeft === 0 ? 'today' : "in {$this->daysLeft} day".($this->daysLeft === 1 ? '' : 's');

        $replyTo = app(SettingsService::class)->get('contact_email');

        return new Envelope(
            subject: "Your {$app} subscription will expire {$when}",
            replyTo: $replyTo ? [new Address($replyTo, $app)] : [],
        );
    }

    public function content(): Content
    {
        $settings = app(SettingsService::class)->public();

        return new Content(markdown: 'mail.subscription-expiring', with: [
            'name' => $this->user->name,
            'endDate' => $this->subscription->end_date->format('d M Y'),
            'daysLeft' => $this->daysLeft,
            'appName' => $settings['app_name'],
            'contactPhone' => $settings['contact_phone'],
            'contactEmail' => $settings['contact_email'],
            'url' => route('user.subscription'),
        ]);
    }
}
