<x-guest :title="__('Choose a new password')">
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" :label="__('Email')" type="email" :value="$email" required />
        <x-field name="password" :label="__('New password')" type="password" required :help="__('At least 8 characters.')" />
        <x-field name="password_confirmation" :label="__('Confirm new password')" type="password" required />
        <button class="btn btn-primary w-full py-3">{{ __('Save new password') }}</button>
    </form>
</x-guest>
