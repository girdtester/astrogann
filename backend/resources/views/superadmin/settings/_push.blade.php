{{-- Push notifications (Firebase) — D-89. Separate forms (file upload / actions) below the main settings form. --}}
<x-card title="Push notifications (Firebase)">
    @if ($push['info'])
        <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
            @if ($push['enabled'])
                <x-badge color="green">ON</x-badge>
            @else
                <x-badge color="yellow">OFF</x-badge>
            @endif
            <span>Project <strong>{{ $push['info']['project_id'] }}</strong></span>
            <span class="text-gray-500">· {{ $push['info']['client_email'] }}</span>
        </div>
    @else
        <p class="mb-4 text-sm text-gray-600">
            Not set up yet. Users still get in-app notifications. To send notifications to phones, upload the Firebase key:
            Firebase console → <em>Project settings</em> → <em>Service accounts</em> → <em>Generate new private key</em> → upload that <code>.json</code> file here.
        </p>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('superadmin.settings.push.upload') }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <x-input-label for="firebase_key" :value="$push['info'] ? 'Replace Firebase key (.json)' : 'Firebase key (.json)'" />
            <input id="firebase_key" name="firebase_key" type="file" accept=".json,application/json" required
                class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-olive-50 file:px-4 file:py-2 file:text-olive-700">
            <x-input-error :messages="$errors->get('firebase_key')" />
            <p class="text-xs text-gray-500">Stored privately on the server (not downloadable from the website).</p>
            <x-primary-button>Upload key</x-primary-button>
        </form>

        @if ($push['info'])
            <div class="space-y-4">
                <form method="POST" action="{{ route('superadmin.settings.push.toggle') }}" class="space-y-2 text-sm">
                    @csrf
                    <label class="flex items-center gap-2">
                        <input type="hidden" name="push_enabled" value="0">
                        <input type="checkbox" name="push_enabled" value="1" class="rounded border-gray-300 text-olive-600" @checked($push['enabled'])>
                        <span>Send push notifications to phones when a new entry is added</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="hidden" name="push_expired_users" value="0">
                        <input type="checkbox" name="push_expired_users" value="1" class="rounded border-gray-300 text-olive-600" @checked(($settings['push_expired_users'] ?? '1') === '1')>
                        <span>Also push to users without an active subscription (with a "renew your subscription" text)</span>
                    </label>
                    <x-secondary-button type="submit">Save push options</x-secondary-button>
                </form>

                <div class="flex flex-wrap gap-2">
                    <x-action :action="route('superadmin.settings.push.check')" variant="info" icon="check">Check connection</x-action>
                    <x-action :action="route('superadmin.settings.push.remove')" method="DELETE" variant="warning" icon="trash"
                        confirm="Remove the Firebase key? Phones will stop getting push notifications.">Remove key</x-action>
                </div>

                <form method="POST" action="{{ route('superadmin.settings.push.test') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="grow"><x-field name="email" label="Send a test push to user (email)" type="email" :value="old('email')" required /></div>
                    <x-primary-button>Send test</x-primary-button>
                </form>
            </div>
        @endif
    </div>
</x-card>
