<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mail check for the live server (D-98). Plain-text report: current mail settings, which SMTP hosts/ports the
 * hosting can reach (GoDaddy blocks outside SMTP such as smtp.gmail.com), and an optional test e-mail.
 *
 *   https://your-domain/diagnostics/mail/{CRON_TOKEN}                         → settings + port check
 *   https://your-domain/diagnostics/mail/{CRON_TOKEN}?to=you@gmail.com        → + send with the current .env settings
 *   …&via=sendmail | via=localhost                                            → + try the hosting's own mail server
 *
 * Also open for a logged-in Super Admin at /superadmin/diagnostics/mail (same query options).
 */
class MailDiagnosticsController extends Controller
{
    public function withToken(Request $request, string $token): Response
    {
        $expected = (string) config('astrogann.cron_token');
        if (strlen($expected) < 32 || ! hash_equals($expected, $token)) {
            abort(404);
        }

        return $this->report($request);
    }

    public function report(Request $request): Response
    {
        @set_time_limit(120);
        $out = [];
        $line = function (string $s = '') use (&$out) { $out[] = $s; };

        $line('ASTRO GANN — mail check  '.now()->format('d M Y, h:i:s A').' (IST)');
        $line(str_repeat('=', 60));
        $line('PHP '.PHP_VERSION.' · Laravel '.app()->version().' · APP_ENV='.config('app.env'));
        if (config('app.debug')) {
            $line('!! APP_DEBUG=true — set APP_DEBUG=false in .env on the live site (error pages show code + paths).');
        }

        $mailer = (string) config('mail.default');
        $cfg = (array) config("mail.mailers.$mailer");
        $line();
        $line('CURRENT SETTINGS (.env)');
        $line('  MAIL_MAILER      '.$mailer);
        if (($cfg['transport'] ?? $mailer) === 'smtp') {
            $line('  MAIL_HOST        '.($cfg['host'] ?? '-'));
            $line('  MAIL_PORT        '.($cfg['port'] ?? '-'));
            $line('  MAIL_ENCRYPTION  '.(($cfg['scheme'] ?? $cfg['encryption'] ?? null) ?: '(none)'));
            $line('  MAIL_USERNAME    '.(($cfg['username'] ?? null) ?: '(none)'));
            $line('  MAIL_PASSWORD    '.(($cfg['password'] ?? null) ? 'set ('.strlen((string) $cfg['password']).' characters)' : '(empty)'));
        }
        $line('  MAIL_FROM        '.config('mail.from.address').' ("'.config('mail.from.name').'")');
        $line('  REPLY-TO         '.(config('mail.reply_to.address') ?: '(none)'));
        $line('  Queue            '.config('queue.default'));

        // Which mail servers can this hosting reach?
        $domain = parse_url((string) config('app.url'), PHP_URL_HOST) ?: $request->getHost();
        $domain = preg_replace('/^www\./', '', $domain);
        $targets = array_unique(array_filter([
            ($cfg['host'] ?? null) && ($cfg['port'] ?? null) ? $cfg['host'].':'.$cfg['port'] : null,
            'localhost:25', 'localhost:465', 'localhost:587',
            "mail.$domain:465", "mail.$domain:587",
            'relay-hosting.secureserver.net:25',
            'smtp.gmail.com:587', 'smtp.gmail.com:465',
        ]));
        $line();
        $line('CAN THE SERVER REACH THESE MAIL SERVERS? (4 s each)');
        foreach ($targets as $t) {
            [$h, $p] = explode(':', $t);
            $t0 = microtime(true);
            $fp = @fsockopen($h, (int) $p, $errno, $errstr, 4);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            if ($fp) {
                fclose($fp);
                $line(sprintf('  OPEN     %-40s %5d ms', $t, $ms));
            } else {
                $line(sprintf('  BLOCKED  %-40s %s', $t, Str::limit(trim($errstr) ?: "error $errno", 60)));
            }
        }
        $sendmail = '/usr/sbin/sendmail';
        $line('  '.(is_executable($sendmail) ? 'FOUND   ' : 'MISSING ').' '.$sendmail.' (for MAIL_MAILER=sendmail)');

        // Optional test e-mail.
        $to = (string) $request->query('to', '');
        if ($to !== '') {
            $line();
            if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $line('TEST E-MAIL: "'.$to.'" is not a valid e-mail address.');
            } else {
                $via = (string) $request->query('via', 'current');
                $line("TEST E-MAIL to $to via ".$via);
                $line('  '.$this->send($to, $via));
                $line('  (If it says SENT, check the inbox and the Spam folder.)');
            }
        } else {
            $line();
            $line('Send a test: add  ?to=you@example.com  (current settings)');
            $line('             or   ?to=you@example.com&via=sendmail   or   &via=localhost   (hosting mail server)');
        }

        $line();
        $line('HOW TO READ THIS');
        $line('  smtp.gmail.com BLOCKED → GoDaddy does not allow Gmail SMTP. Use the hosting mail server instead:');
        $line('    MAIL_MAILER=sendmail   (simplest)            or');
        $line('    MAIL_MAILER=smtp, MAIL_HOST=localhost, MAIL_PORT=25, MAIL_ENCRYPTION=null, MAIL_USERNAME=null, MAIL_PASSWORD=null');
        $line("    MAIL_FROM_ADDRESS=noreply@$domain   (create it in cPanel → Email Accounts)");
        $line('    MAIL_REPLY_TO_ADDRESS=ganndecoder@gmail.com   (replies still go to Gmail)');
        $line('  Then cPanel cron once:  …/artisan optimize:clear   and test again.');

        return response(implode("\n", $out)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    private function send(string $to, string $via): string
    {
        $config = match ($via) {
            'sendmail' => ['transport' => 'sendmail', 'path' => '/usr/sbin/sendmail -bs -i'],
            'localhost' => ['transport' => 'smtp', 'host' => 'localhost', 'port' => 25, 'timeout' => 10, 'scheme' => 'smtp'],
            default => null,
        };

        $body = 'Test e-mail from '.config('app.name').' ('.config('app.url').") via $via at ".now()->format('d M Y, h:i A')
            .".\n\nIf you can read this, e-mail sending works with these settings.";
        $t0 = microtime(true);

        try {
            $mailer = $config ? Mail::build($config) : Mail::mailer();
            $mailer->raw($body, function ($m) use ($to, $via) {
                $m->to($to)->subject(config('app.name')." — test e-mail ($via)");
                if (config('mail.reply_to.address')) {
                    $m->replyTo(config('mail.reply_to.address'));
                }
            });
        } catch (Throwable $e) {
            return 'FAILED after '.round(microtime(true) - $t0, 1).' s: '.Str::limit($e->getMessage(), 400);
        }

        return 'SENT in '.round(microtime(true) - $t0, 1).' s';
    }
}
