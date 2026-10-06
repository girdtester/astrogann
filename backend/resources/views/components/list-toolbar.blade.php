{{-- Result count + page size + reset filters (D-77). --}}
@props(['paginator'])
<div class="mb-3 flex flex-wrap items-center justify-between gap-3 text-sm text-gray-600">
    <div>
        @if ($paginator->total() > 0)
            Showing <strong>{{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}</strong>
            of <strong>{{ number_format($paginator->total()) }}</strong>
        @else
            No results
        @endif
    </div>
    <div class="flex items-center gap-3">
        @if (collect(request()->except(['page', 'per_page']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty())
            <x-action :href="url()->current()">Reset filters</x-action>
        @endif
        <form method="GET" class="flex items-center gap-2">
            @foreach (request()->except(['per_page', 'page']) as $k => $v)
                @if (is_scalar($v)) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
            @endforeach
            <label for="per_page">Per page</label>
            <select id="per_page" name="per_page" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                @foreach ([20, 50, 100] as $n)
                    <option value="{{ $n }}" @selected($paginator->perPage() === $n)>{{ $n }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>
