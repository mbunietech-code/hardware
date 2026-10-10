@props(['action', 'label' => null])
<form method="POST" action="{{ $action }}" class="flex flex-wrap items-end gap-2" x-data
      @submit="if (!confirm(@js(__('This will reverse the record. Continue?')))) $event.preventDefault()">
    @csrf
    <div class="min-w-64 flex-1">
        <label class="label">{{ __('Reason for correction') }} <span class="text-red-600">*</span></label>
        <input name="reason" class="input" required maxlength="255" placeholder="{{ __('e.g. Entered wrong quantity') }}">
    </div>
    <button class="btn btn-danger">{{ $label ?? __('Void') }}</button>
</form>
