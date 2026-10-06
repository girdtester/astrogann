<?php

namespace App\Services;

use App\Events\EntryCreated;
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
        $category = $this->activeCategory($data['category_id']);
        $path = $this->storeImage($image);

        try {
            $entry = DB::transaction(fn () => Entry::create([
                'category_id' => $category->id,
                'admin_id' => $admin->id,
                'image_path' => $path,
                'entry_date' => $data['entry_date'],
                'entry_time' => $this->time($data['entry_time']),
            ]));
        } catch (Throwable $e) {
            $this->deleteImage($path);
            throw $e;
        }

        EntryCreated::dispatch($entry->setRelation('category', $category));

        $this->logs->log('entry.created', $entry, "Entry added to {$category->name} ({$entry->entry_date->toDateString()} {$entry->timeLabel()})", $admin);

        return $entry->setRelation('category', $category);
    }

    /**
     * Edit an entry (DECISIONS D-71). A new image replaces the old file; no new notification is sent.
     */
    public function update(Entry $entry, array $data, ?UploadedFile $image, User $by): Entry
    {
        $category = $this->activeCategory($data['category_id']);
        $oldPath = $entry->image_path;
        $newPath = $image ? $this->storeImage($image) : null;

        try {
            $entry->update([
                'category_id' => $category->id,
                'entry_date' => $data['entry_date'],
                'entry_time' => $this->time($data['entry_time']),
                'image_path' => $newPath ?? $oldPath,
            ]);
        } catch (Throwable $e) {
            if ($newPath) {
                $this->deleteImage($newPath);
            }
            throw $e;
        }

        if ($newPath) {
            $this->deleteImage($oldPath);
        }

        $this->logs->log('entry.updated', $entry,
            "Entry #{$entry->id} edited ({$category->name} {$entry->entry_date->toDateString()} {$entry->timeLabel()}".($newPath ? ', new image' : '').')', $by);

        return $entry->setRelation('category', $category);
    }

    /** Delete an entry and its image file (DECISIONS D-71). */
    public function delete(Entry $entry, User $by): void
    {
        $description = "Entry #{$entry->id} deleted ({$entry->category?->name} {$entry->entry_date->toDateString()} {$entry->timeLabel()}, added by {$entry->admin?->email})";
        $path = $entry->image_path;

        $entry->delete();
        $this->deleteImage($path);
        $this->logs->log('entry.deleted', null, $description, $by);
    }

    private function activeCategory(int|string $id): Category
    {
        return Category::query()->active()->find($id) ?? throw new InvalidArgumentException('Choose an active category.');
    }

    /** Store under a random name; extension from the sniffed MIME type, never the client file name. */
    private function storeImage(UploadedFile $image): string
    {
        $ext = match ($image->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new InvalidArgumentException('Unsupported image type.'),
        };
        $dir = config('astrogann.entry_upload_dir');
        $name = Str::random(40).'.'.$ext;
        Storage::disk('uploads')->putFileAs($dir, $image, $name);

        return 'uploads/'.$dir.'/'.$name;
    }

    /** Remove an image file given its stored path ("uploads/entries/x.png"); only inside the uploads disk. */
    public function deleteImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/') && ! str_contains($path, '..')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }

    private function time(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
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
