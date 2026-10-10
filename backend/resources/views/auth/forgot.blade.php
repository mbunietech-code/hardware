<x-guest :title="__('Forgot password?')">
    <p class="mt-2 text-sm text-slate-500">{{ __('Enter your email and we will send you a link to choose a new password.') }}</p>
    @if (session('success'))
        <div class="flash mt-6 border-emerald-200 bg-emerald-50 text-emerald-800"><x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0" />{{ session('success') }}</div>
    @endif
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
        @csrf
        <x-field name="email" :label="__('Email')" type="email" required autofocus />
        <button class="btn btn-primary w-full py-3">{{ __('Send reset link') }}</button>
    </form>
    <p class="mt-4 text-xs text-slate-500">{{ __('No email on your account? Ask the Super Admin to set a new password for you (Users → Edit).') }}</p>
</x-guest>
