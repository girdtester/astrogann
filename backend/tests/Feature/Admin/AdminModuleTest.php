<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Entry;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $this->seed(SettingsSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->category = Category::factory()->create(['name' => 'Nifty 50']);
        $this->signIn($this->admin);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'category_id' => $this->category->id,
            'entry_date' => today()->toDateString(),
            'entry_time' => '09:30',
            'image' => UploadedFile::fake()->image('chart.png', 800, 480),
        ];
    }

    public function test_admin_screens_render(): void
    {
        $this->get('/admin')->assertOk()->assertSee('Add new entry');
        $this->get(route('admin.categories.index'))->assertOk()->assertSee('Nifty 50');
        $this->get(route('admin.entries.create'))->assertOk();
        $this->get(route('admin.entries.index'))->assertOk();
    }

    public function test_admin_can_add_an_entry_and_image_gets_a_random_name(): void
    {
        $this->post(route('admin.entries.store'), $this->payload())
            ->assertRedirect(route('admin.entries.index'))
            ->assertSessionHas('success');

        $entry = Entry::sole();
        $this->assertSame($this->admin->id, $entry->admin_id);
        $this->assertSame('09:30', $entry->timeLabel());
        $this->assertMatchesRegularExpression('#^uploads/entries/[A-Za-z0-9]{40}\.png$#', $entry->image_path);
        Storage::disk('uploads')->assertExists(substr($entry->image_path, strlen('uploads/')));
        $this->assertStringNotContainsString('chart', $entry->image_path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'entry.created', 'user_id' => $this->admin->id]);
    }

    public function test_upload_validation(): void
    {
        $cases = [
            'pdf' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            'php disguised' => UploadedFile::fake()->createWithContent('shell.php.png', '<?php echo 1;'),
            'too big' => UploadedFile::fake()->image('big.jpg')->size(config('astrogann.entry_image_max_kb') + 1),
            'gif' => UploadedFile::fake()->image('anim.gif'),
        ];

        foreach ($cases as $label => $file) {
            $this->post(route('admin.entries.store'), $this->payload(['image' => $file]))
                ->assertSessionHasErrors('image', "Case: {$label}");
        }
        $this->assertSame(0, Entry::count());
    }

    public function test_inactive_category_and_bad_date_time_are_rejected(): void
    {
        $inactive = Category::factory()->inactive()->create();

        $this->post(route('admin.entries.store'), $this->payload(['category_id' => $inactive->id]))->assertSessionHasErrors('category_id');
        $this->post(route('admin.entries.store'), $this->payload(['entry_time' => '25:99']))->assertSessionHasErrors('entry_time');
        $this->post(route('admin.entries.store'), $this->payload(['entry_date' => 'yesterday']))->assertSessionHasErrors('entry_date');
        $this->assertSame(0, Entry::count());
    }

    public function test_admin_sees_only_own_entries(): void
    {
        $other = User::factory()->admin()->create();
        Entry::factory()->create(['admin_id' => $this->admin->id, 'category_id' => $this->category->id, 'entry_date' => '2026-01-01']);
        Entry::factory()->create(['admin_id' => $other->id, 'category_id' => $this->category->id, 'entry_date' => '2026-02-02']);

        $this->get(route('admin.entries.index'))->assertOk()
            ->assertSee('01 Jan 2026')
            ->assertDontSee('02 Feb 2026');
    }

    public function test_default_admin_cannot_edit_or_delete_entries(): void
    {
        $entry = Entry::factory()->create(['admin_id' => $this->admin->id, 'category_id' => $this->category->id]);

        $this->assertFalse($this->admin->can('update', $entry));
        $this->assertFalse($this->admin->can('delete', $entry));
        $this->get(route('admin.entries.edit', $entry))->assertForbidden();
        $this->put(route('admin.entries.update', $entry), $this->payload())->assertForbidden();
        $this->delete(route('admin.entries.destroy', $entry))->assertForbidden();
        $this->get(route('admin.entries.index'))->assertDontSee(route('admin.entries.edit', $entry));
        $this->assertNotNull($entry->fresh());
    }

    public function test_admin_granted_edit_and_delete_works_on_own_entries_only(): void
    {
        $this->admin->directPermissions()->sync(\App\Models\Permission::whereIn('key', ['entries.edit', 'entries.delete'])->pluck('id'));
        $this->admin->flushPermissionCache();
        $own = Entry::factory()->create(['admin_id' => $this->admin->id, 'category_id' => $this->category->id]);
        $other = Entry::factory()->create(['category_id' => $this->category->id]);

        $this->put(route('admin.entries.update', $own), $this->payload(['entry_time' => '11:45', 'image' => null]))
            ->assertRedirect(route('admin.entries.index'));
        $this->assertSame('11:45', $own->fresh()->timeLabel());

        $this->put(route('admin.entries.update', $other), $this->payload())->assertForbidden();
        $this->delete(route('admin.entries.destroy', $other))->assertForbidden();
        $this->delete(route('admin.entries.destroy', $own))->assertRedirect();
        $this->assertNull($own->fresh());
        $this->assertNotNull($other->fresh());
    }

    public function test_admin_has_no_management_access(): void
    {
        $trader = User::factory()->create();

        $this->get(route('superadmin.users.index'))->assertForbidden();
        $this->post(route('superadmin.users.store'), ['name' => 'x'])->assertForbidden();
        $this->get(route('superadmin.subscriptions.show', $trader))->assertForbidden();
        $this->post(route('superadmin.subscriptions.action', $trader), ['action' => 'activate'])->assertForbidden();
        $this->post(route('superadmin.categories.store'), ['name' => 'x', 'code' => 'X', 'status' => 'active'])->assertForbidden();
        $this->put(route('superadmin.categories.update', $this->category), ['name' => 'Hacked'])->assertForbidden();
        $this->assertSame('Nifty 50', $this->category->fresh()->name);
        $this->get(route('superadmin.entries.index'))->assertForbidden();
    }

    public function test_staff_without_entry_permission_cannot_add_entries(): void
    {
        $staff = User::factory()->staffWith('users.manage')->create();
        $this->flushSession();
        $this->signIn($staff);

        $this->get(route('admin.entries.create'))->assertForbidden();
        $this->post(route('admin.entries.store'), $this->payload())->assertForbidden();
        $this->assertSame(0, Entry::count());
    }

    public function test_trader_cannot_reach_admin_area(): void
    {
        $this->flushSession();
        $this->signIn(User::factory()->create());

        $this->get(route('admin.entries.create'))->assertForbidden();
        $this->post(route('admin.entries.store'), $this->payload())->assertForbidden();
    }
}
