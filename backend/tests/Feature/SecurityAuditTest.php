<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 9 guard rails: every route is protected unless it is on the explicit public list, every protected
 * management route is permission-guarded, and security headers are present.
 */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    /** Routes that are meant to be reachable without logging in. */
    private const PUBLIC = [
        'GET /', 'GET login', 'POST login', 'GET forgot-password', 'POST forgot-password', 'GET reset-password/{token}',
        'POST reset-password', 'GET up', 'GET api/settings', 'POST api/auth/login', 'POST api/auth/register', 'POST api/auth/forgot-password', 'GET register', 'POST register', 'GET cron/run/{token}', 'GET diagnostics/mail/{token}', 'GET storage/{path}', 'PUT storage/{path}',
        'GET sanctum/csrf-cookie',
    ];

    public function test_every_non_public_route_requires_authentication(): void
    {
        $unprotected = [];

        foreach (Route::getRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $key = $method.' '.$route->uri();
                if (in_array($key, self::PUBLIC, true)) {
                    continue;
                }
                $mw = $route->gatherMiddleware();
                if (! in_array('auth', $mw, true) && ! in_array('auth:sanctum', $mw, true)) {
                    $unprotected[] = $key;
                }
            }
        }

        $this->assertSame([], $unprotected, 'Unprotected routes: '.implode(', ', $unprotected));
    }

    public function test_management_and_admin_routes_are_role_and_permission_guarded(): void
    {
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $mw = $route->gatherMiddleware();
            $hasRole = (bool) array_filter($mw, fn ($m) => is_string($m) && str_starts_with($m, 'role:'));
            $hasCan = (bool) array_filter($mw, fn ($m) => is_string($m) && str_starts_with($m, 'can:'));

            $isDashboard = in_array($uri, ['superadmin', 'admin', 'user'], true);
            if (str_starts_with($uri, 'superadmin') || str_starts_with($uri, 'admin') || str_starts_with($uri, 'user')) {
                if (! $hasRole || (! $isDashboard && ! str_starts_with($uri, 'user') && ! $hasCan)) {
                    $missing[] = $uri;
                }
            }
            if (str_starts_with($uri, 'api/admin') && (! $hasRole || ! $hasCan)) {
                $missing[] = $uri;
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }

    public function test_api_authenticated_routes_enforce_single_session(): void
    {
        foreach (Route::getRoutes() as $route) {
            $mw = $route->gatherMiddleware();
            if (in_array('auth:sanctum', $mw, true)) {
                $this->assertContains('session.single', $mw, $route->uri().' lacks session.single');
            }
        }
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->getJson('/api/settings')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_password_change_is_logged(): void
    {
        $user = User::factory()->create();
        $this->signIn($user)->put('/password', [
            'current_password' => 'password', 'password' => 'new-password1', 'password_confirmation' => 'new-password1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.password_changed', 'user_id' => $user->id]);
    }

    public function test_no_html_is_rendered_unescaped_from_user_input(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>']);
        $this->signIn($user)->get('/profile')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_create_superadmin_command(): void
    {
        $this->artisan("astrogann:create-superadmin", ["name" => "Owner", "email" => "Owner@Example.com", "--password" => "secret-pass1"])
            ->assertSuccessful();
        $u = User::where("email", "owner@example.com")->sole();
        $this->assertTrue($u->isSuperAdmin());
        $this->post("/login", ["email" => "owner@example.com", "password" => "secret-pass1"])->assertRedirect("/superadmin");

        $this->artisan("astrogann:create-superadmin", ["name" => "Again", "email" => "owner@example.com"])->assertFailed();
    }
}
