@foreach (['success' => 'bg-green-50 text-green-800 border-green-200', 'error' => 'bg-yellow-50 text-yellow-800 border-yellow-200', 'status' => 'bg-blue-50 text-blue-800 border-blue-200'] as $key => $classes)
    @if (session($key) && ! in_array(session($key), ['profile-updated', 'password-updated'], true))
        <div class="mb-4 rounded-md border px-4 py-3 text-sm {{ $classes }}" role="alert">{{ session($key) }}</div>
    @endif
@endforeach
@if ($errors->any() && ! request()->routeIs('profile.*'))
    <div class="mb-4 rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800" role="alert">
        Please fix the highlighted fields.
    </div>
@endif
