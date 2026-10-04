<x-layout :title="__('My profile')">
    <x-page-header icon="user" :title="__('My profile')" :subtitle="auth()->user()->email.' · '.auth()->user()->roleLabel()" />
    <form method="POST" action="{{ route('profile.password') }}" class="card card-body max-w-lg space-y-4">
        @csrf @method('PUT')
        <h2 class="font-semibold">{{ __('Change password') }}</h2>
        <x-field name="current_password" :label="__('Current password')" type="password" required />
        <x-field name="password" :label="__('New password')" type="password" required :help="__('At least 8 characters.')" />
        <x-field name="password_confirmation" :label="__('Confirm new password')" type="password" required />
        <button class="btn btn-primary">{{ __('Change password') }}</button>
    </form>
</x-layout>
