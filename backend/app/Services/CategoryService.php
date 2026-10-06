<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
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

    /** Only categories without entries can be deleted; otherwise deactivate them (entries are immutable). */
    public function delete(Category $category, User $by): void
    {
        if ($category->entries()->exists()) {
            throw new InvalidArgumentException('This category has entries. Deactivate it instead of deleting.');
        }
        $this->logs->log('category.deleted', null, "Deleted category {$category->name}", $by);
        $category->delete();
    }
}
