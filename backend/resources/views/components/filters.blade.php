@props(['shops' => null, 'dates' => true])
<form method="GET" class="card no-print flex flex-wrap items-end gap-3 p-4 sm:p-5">
    <div class="hidden h-[42px] items-center pr-1 text-slate-400 lg:flex"><x-icon name="filter" /></div>
    @if ($dates)
        <div><label class="label">{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}" class="input"></div>
        <div><label class="label">{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}" class="input"></div>
    @endif
    @if ($shops && auth()->user()->isSuperAdmin())
        <div><label class="label">{{ __('Shop') }}</label>
            <select name="shop_id" class="input"><option value="">{{ __('All shops') }}</option>
                @foreach ($shops as $id => $name)<option value="{{ $id }}" @selected(request('shop_id') == $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
    @endif
    {{ $slot }}
    <div class="flex gap-2">
        <button class="btn btn-primary"><x-icon name="search" class="h-4 w-4" />{{ __('Filter') }}</button>
        <a href="{{ url()->current() }}" class="btn">{{ __('Reset') }}</a>
    </div>
</form>
