<?php

namespace App\Jobs;

use App\Services\ActivityLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends ONE e-mail to ONE person, at a safe pace (DECISIONS D-87). All subscription e-mails go through this job.
 *
 * The "outgoing-mail" rate limit (MAIL_PER_MINUTE, default 20) keeps us under Gmail / GoDaddy sending limits:
 * when it is reached the job is put back and sent a little later — nothing is dropped. Retries for up to 2 days;
 * gives up only after 3 real SMTP errors (then it is in failed_jobs + Reports / Logs as job.failed).
 */
class SendMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $maxExceptions = 3;

    public int $timeout = 60;

    public function __construct(public readonly string $to, public readonly Mailable $mailable) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new RateLimited('outgoing-mail'))->releaseAfter(30)];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDays(2);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        Mail::to($this->to)->send($this->mailable);
    }

    public function failed(Throwable $e): void
    {
        app(ActivityLogService::class)->log('job.failed', null, 'E-mail '.class_basename($this->mailable)." to {$this->to} failed: ".$e->getMessage());
    }
}
