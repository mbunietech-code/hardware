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
    <p class="text-xs text-slate-400">{{ __('Photo credits') }}:
        @foreach (\App\Support\Photos::CREDITS as $c)<a href="{{ $c['url'] }}" target="_blank" rel="noopener" class="hover:text-slate-600">{{ $c['text'] }}</a>@if (! $loop->last) · @endif @endforeach
    </p>
</x-layout>
