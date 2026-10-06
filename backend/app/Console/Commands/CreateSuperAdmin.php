<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\PermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates a Super Admin account (production has no demo seeder). Non-interactive so it also works from a
 * one-off cPanel cron line. Prints the generated password once.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'astrogann:create-superadmin {name} {email} {--password= : Use this password instead of a random one}';

    protected $description = 'Create a Super Admin account';

    public function handle(PermissionService $permissions, ActivityLogService $logs): int
    {
        $email = strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid e-mail address.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error("An account with {$email} already exists.");

            return self::FAILURE;
        }

        $permissions->ensureSystemRoles();
        $password = $this->option('password') ?: Str::password(16, symbols: false);

        $user = User::create([
            'name' => $this->argument('name'),
            'email' => $email,
            'password' => $password,
            'role_id' => Role::findBySlug(Role::SUPERADMIN)->id,
            'status' => 'active',
        ]);
        $logs->log('superadmin.created', $user, "Super Admin {$email} created from the command line", $user);

        $this->info("Super Admin created: {$email}");
        if (! $this->option('password')) {
            $this->line("Password: {$password}");
            $this->warn('Change it after the first login (Profile → Update Password).');
        }

        return self::SUCCESS;
    }
}
