<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Entry;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Dynamic categories (spec 3.9): names, order (sort_order) and active flag drive the user tabs.
 */
class CategoryService
{
    public function __construct(private readonly ActivityLogService $logs) {}

    /** Active categories in tab order — the single source for web and mobile tabs. */
    public function activeTabs(): Collection
    {
        return Category::query()->active()->ordered()->get();
    }

    public function create(array $data, User $by): Category
    {
        $category = Category::create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'status' => $data['status'] ?? 'active',
            'sort_order' => $data['sort_order'] ?? ((int) Category::max('sort_order') + 1),
        ]);
        $this->logs->log('category.created', $category, "Created category {$category->name}", $by);

        return $category;
    }

    public function update(Category $category, array $data, User $by): Category
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        $category->update($data);
        $this->logs->log('category.updated', $category, "Updated category {$category->name}", $by);

        return $category;
    }

    public function canDelete(Category $category, User $by): bool
    {
        return ! $category->entries()->exists() || $by->hasPermission(Permissions::RECORDS_FORCE_DELETE);
    }

    /**
     * Empty categories can be deleted with categories.manage. A category with entries needs
     * records.force_delete (DECISIONS D-72): its entries and their images are deleted too.
     */
    public function delete(Category $category, User $by): void
    {
        if (! $this->canDelete($category, $by)) {
            throw new InvalidArgumentException('This category has entries. Deactivate it instead, or ask the Super Admin.');
        }

        $count = 0;
        $category->entries()->select(['id', 'image_path', 'thumb_path'])->chunkById(200, function ($entries) use (&$count) {
            foreach ($entries as $entry) {
                app(EntryService::class)->deleteImage($entry->image_path);
                app(EntryService::class)->deleteImage($entry->thumb_path);
            }
            $count += Entry::whereIn('id', $entries->pluck('id'))->delete();
        });

        $this->logs->log('category.deleted', null, "Deleted category {$category->name}".($count ? " and its {$count} entries" : ''), $by);
        $category->delete();
    }
}
