<x-layout :title="__('Import products')">
    <x-page-header icon="download" :title="__('Import products')" :subtitle="__('Add or update many products at once from Excel or CSV.')">
        <a href="{{ route('products.index') }}" class="btn"><x-icon name="box" class="h-4 w-4" />{{ __('Products') }}</a>
    </x-page-header>

    @if ($result = session('import'))
        @if ($result['errors'])
            <div class="card overflow-hidden">
                <div class="card-header"><div class="card-title text-rose-700"><x-icon name="alert" class="h-5 w-5" />{{ __('Rows with problems (not saved)') }}</div></div>
                <table class="table"><thead><tr><th>{{ __('Row') }}</th><th>{{ __('Problem') }}</th></tr></thead><tbody>
                    @foreach ($result['errors'] as $row => $message)
                        <tr><td class="font-semibold">{{ $row }}</td><td class="wrap">{{ $message }}</td></tr>
                    @endforeach
                </tbody></table>
            </div>
        @endif
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
        <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="card card-body space-y-5">
            @csrf
            <div>
                <label class="label">{{ __('File (.xlsx, .xls or .csv)') }} <span class="text-rose-600">*</span></label>
                <input type="file" name="file" required accept=".xlsx,.xls,.csv" class="input file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:font-semibold file:text-brand-700">
                @error('file')<p class="error">{{ $message }}</p>@enderror
            </div>
            <x-select name="shop_id" :label="__('Set the stock column for shop')" :options="$shops" :value="auth()->user()->shop_id" :placeholder="__('— Do not change stock —')" />
            <button class="btn btn-primary"><x-icon name="download" class="h-4 w-4 rotate-180" />{{ __('Import') }}</button>
        </form>

        <div class="card card-body space-y-4 text-sm">
            <div class="card-title"><x-icon name="sparkles" class="h-5 w-5 text-brand-600" />{{ __('How it works') }}</div>
            <ol class="list-decimal space-y-2 pl-5 text-slate-600">
                <li>{{ __('Download the Excel template and fill one product per row.') }}</li>
                <li>{{ __('Required columns: code, name, selling_price. Optional: category, unit, cost_price, reorder_level, stock.') }}</li>
                <li>{{ __('If a code already exists, that product is updated; otherwise a new product is created.') }}</li>
                <li>{{ __('The stock column sets the counted stock for the chosen shop and is recorded as a stock adjustment.') }}</li>
            </ol>
            <a href="{{ route('products.import.template') }}" class="btn w-full"><x-icon name="download" class="h-4 w-4" />{{ __('Download Excel template') }}</a>
        </div>
    </div>
</x-layout>
