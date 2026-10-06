@foreach (['success' => 'bg-green-50 text-green-800 border-green-200', 'error' => 'bg-red-50 text-red-800 border-red-200', 'status' => 'bg-blue-50 text-blue-800 border-blue-200'] as $key => $classes)
    @if (session($key) && ! in_array(session($key), ['profile-updated', 'password-updated'], true))
        <div class="mb-4 rounded-md border px-4 py-3 text-sm {{ $classes }}" role="alert">{{ session($key) }}</div>
    @endif
@endforeach
@if ($errors->any() && ! request()->routeIs('profile.*'))
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        Please fix the highlighted fields.
    </div>
@endif
