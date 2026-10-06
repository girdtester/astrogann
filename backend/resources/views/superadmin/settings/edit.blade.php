<x-page title="System Settings">
    <form method="POST" action="{{ route('superadmin.settings.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <x-card title="App">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-field name="app_name" label="App name" :value="$settings['app_name']" required />
                <div class="md:col-span-2">
                    <x-input-label for="notice_text" value="Notice text (shown to users at the top of the entries screen; leave empty for none)" />
                    <textarea id="notice_text" name="notice_text" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">{{ old('notice_text', $settings['notice_text']) }}</textarea>
                    <x-input-error :messages="$errors->get('notice_text')" class="mt-1" />
                </div>
            </div>
        </x-card>

        <x-card title="Access rules">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-field name="entries_limit_active" label="Entries shown — active subscription" type="number" min="1" :value="$settings['entries_limit_active']" required />
                <x-field name="entries_limit_expired" label="Entries shown — expired / no subscription" type="number" min="0" :value="$settings['entries_limit_expired']" required />
                <x-field name="reminder_days" label="Expiry reminder e-mail (days before)" type="number" min="1" :value="$settings['reminder_days']" required />
            </div>
            <label class="mt-4 flex items-start gap-2 text-sm">
                <input type="hidden" name="notify_expired_users" value="0">
                <input type="checkbox" name="notify_expired_users" value="1" class="mt-0.5 rounded border-gray-300 text-olive-600" @checked(old('notify_expired_users', $settings['notify_expired_users'] ?? '1') === '1')>
                <span>Also notify users whose subscription is expired (or who never subscribed) about new entries, with a "renew your subscription" message. They never get a push notification.</span>
            </label>
        </x-card>

        <x-card title="&quot;Show More&quot; / contact admin">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-3">
                    <x-input-label for="show_more_text" value="Show More text" />
                    <textarea id="show_more_text" name="show_more_text" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" required>{{ old('show_more_text', $settings['show_more_text']) }}</textarea>
                    <x-input-error :messages="$errors->get('show_more_text')" class="mt-1" />
                </div>
                <x-field name="contact_phone" label="Phone" :value="$settings['contact_phone']" />
                <x-field name="contact_whatsapp" label="WhatsApp number (digits, with country code)" :value="$settings['contact_whatsapp']" />
                <x-field name="contact_email" label="E-mail" type="email" :value="$settings['contact_email']" />
            </div>
        </x-card>

        <x-primary-button>Save settings</x-primary-button>
    </form>
</x-page>
