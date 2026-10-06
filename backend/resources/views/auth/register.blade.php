<x-guest-layout>
    <h1 class="mb-1 text-lg font-semibold text-gray-900">Create your account</h1>
    <p class="mb-5 text-sm text-gray-600">Free sign-up. You will see the latest entries right away; contact the admin to subscribe for full access.</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <x-field name="name" label="Full name" :value="old('name')" required autofocus autocomplete="name" />
        <x-field name="email" label="Email" type="email" :value="old('email')" required autocomplete="username" />
        <x-field name="phone" label="Mobile number (optional)" type="tel" :value="old('phone')" autocomplete="tel" help="So the admin can reach you about your subscription." />
        <x-field name="password" label="Password" type="password" required autocomplete="new-password" help="At least 8 characters." />
        <x-field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />

        <div class="flex items-center justify-between pt-2">
            <a class="text-sm text-olive-700 hover:underline" href="{{ route('login') }}">Already have an account? Log in</a>
            <x-primary-button>Sign up</x-primary-button>
        </div>
    </form>
</x-guest-layout>
