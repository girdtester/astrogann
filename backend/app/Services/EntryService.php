<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Entries: creation by admins, visibility rules for users (spec 3.4), listings for staff.
 * Entries are immutable (DECISIONS D-19).
 */
class EntryService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly ActivityLogService $logs,
        private readonly SettingsService $settings,
    ) {}

    /** Store the image under a random name in public/uploads/entries and create the entry. */
    public function create(User $admin, array $data, UploadedFile $image): Entry
    {
        $category = Category::query()->active()->find($data['category_id'])
            ?? throw new InvalidArgumentException('Choose an active category.');

        $ext = match ($image->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new InvalidArgumentException('Unsupported image type.'),
        };
        $dir = config('astrogann.entry_upload_dir');
        $name = Str::random(40).'.'.$ext;
        Storage::disk('uploads')->putFileAs($dir, $image, $name);
        $path = 'uploads/'.$dir.'/'.$name;

        try {
            $entry = DB::transaction(fn () => Entry::create([
                'category_id' => $category->id,
                'admin_id' => $admin->id,
                'image_path' => $path,
                'entry_date' => $data['entry_date'],
                'entry_time' => strlen($data['entry_time']) === 5 ? $data['entry_time'].':00' : $data['entry_time'],
            ]));
        } catch (Throwable $e) {
            Storage::disk('uploads')->delete($dir.'/'.$name);
            throw $e;
        }

        $this->logs->log('entry.created', $entry, "Entry added to {$category->name} ({$entry->entry_date->toDateString()} {$entry->timeLabel()})", $admin);

        return $entry->setRelation('category', $category);
    }

    /**
     * Entries a user/trader may see in a category: latest N where N comes from the subscription rule.
     * The limit is applied in the query.
     *
     * @return array{entries: Collection<int, Entry>, limit: int, restricted: bool}
     */
    public function visibleFor(User $user, Category $category): array
    {
        // Staff are not subscribers: they see what an active subscriber sees.
        $active = ! $user->isTrader() || $this->subscriptions->isActiveFor($user, $category);
        $limit = $active ? $this->settings->entriesLimitActive() : $this->settings->entriesLimitExpired();

        $entries = $category->entries()->latestFirst()->limit($limit)->get();

        return ['entries' => $entries, 'limit' => $limit, 'restricted' => ! $active];
    }

    /** Filterable listing for Super Admin (all) and Admin (own only). */
    public function search(array $filters, ?User $onlyAdmin = null, int $perPage = 20): LengthAwarePaginator
    {
        return Entry::query()
            ->with(['category', 'admin'])
            ->when($onlyAdmin, fn (Builder $q) => $q->where('admin_id', $onlyAdmin->id))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when($filters['admin_id'] ?? null, fn (Builder $q, $v) => $q->where('admin_id', $v))
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('entry_date', '>=', $v))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->whereDate('entry_date', '<=', $v))
            ->latestFirst()
            ->paginate($perPage)
            ->withQueryString();
    }
}
