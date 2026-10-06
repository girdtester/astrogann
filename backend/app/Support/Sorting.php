<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Safe, whitelisted sorting for list screens (DECISIONS D-77): ?sort=<key>&dir=asc|desc.
 * Unknown keys fall back to the default; a primary-key tie-breaker keeps pagination stable on large tables.
 */
class Sorting
{
    /**
     * @param  array<string, string|list<string>|\Closure(Builder, string): void>  $allowed  key => column(s) or callback
     * @return array{sort: string, dir: string}
     */
    public static function apply(Builder $query, Request $request, array $allowed, string $default, string $defaultDir = 'desc'): array
    {
        $sort = (string) $request->query('sort', $default);
        $dir = strtolower((string) $request->query('dir', $defaultDir)) === 'asc' ? 'asc' : 'desc';

        if (! array_key_exists($sort, $allowed)) {
            [$sort, $dir] = [$default, $defaultDir];
        }

        $target = $allowed[$sort];
        if ($target instanceof \Closure) {
            $target($query, $dir);
        } else {
            foreach ((array) $target as $column) {
                $query->orderBy($column, $dir);
            }
        }
        $query->orderBy($query->getModel()->getQualifiedKeyName(), $dir);

        return ['sort' => $sort, 'dir' => $dir];
    }

    /**
     * "Deferred join" pagination for big tables: the expensive sort + OFFSET run on primary keys only (narrow rows,
     * often index-only), then just one page of full models is loaded with the query's eager loads, in order.
     */
    public static function paginateDeferred(Builder $query, int $perPage, Request $request): LengthAwarePaginator
    {
        $model = $query->getModel();
        $key = $model->getQualifiedKeyName();
        $page = max(1, (int) $request->query('page', 1));

        $total = (clone $query)->toBase()->reorder()->getCountForPagination();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $ids = (clone $query)->setEagerLoads([])->select($key)->forPage($page, $perPage)->pluck($model->getKeyName())->all();

        $items = $ids === [] ? $model->newCollection() : $model->newQuery()
            ->with($query->getEagerLoads())
            ->whereKey($ids)
            ->get()
            ->sortBy(fn ($m) => array_search($m->getKey(), $ids, false))
            ->values();

        return (new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
            'pageName' => 'page',
        ]))->withQueryString()->onEachSide(1);
    }

    /** Page size from ?per_page (20 / 50 / 100). */
    public static function perPage(Request $request, int $default = 20): int
    {
        $n = (int) $request->query('per_page', $default);

        return in_array($n, [20, 50, 100], true) ? $n : $default;
    }
}
