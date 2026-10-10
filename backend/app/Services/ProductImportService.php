<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Throwable;

/**
 * Bulk create/update products from an Excel or CSV file.
 * Rows are matched by product code: existing codes are updated, new codes created.
 * An optional stock column sets the counted stock for the chosen shop.
 */
class ProductImportService
{
    /** Accepted header names (English and Swahili) for each field. */
    public const COLUMNS = [
        'code' => ['code', 'sku', 'msimbo'],
        'name' => ['name', 'product', 'jina', 'bidhaa'],
        'category' => ['category', 'kundi'],
        'unit' => ['unit', 'kipimo'],
        'cost_price' => ['cost_price', 'cost', 'buying_price', 'bei_ya_kununua', 'gharama'],
        'selling_price' => ['selling_price', 'price', 'bei_ya_kuuza', 'bei'],
        'reorder_level' => ['reorder_level', 'reorder', 'kiwango_cha_kuagiza'],
        'stock' => ['stock', 'opening_stock', 'quantity', 'stoku', 'idadi'],
    ];

    public function __construct(private CatalogService $catalog, private StockService $stock) {}

    /**
     * @return array{created: int, updated: int, stock: int, errors: array<int, string>}
     */
    public function import(string $path, User $user, ?int $shopId): array
    {
        if (! $user->hasPermission('manage_products')) {
            throw new AuthorizationException(__('You are not allowed to manage products.'));
        }
        try {
            $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('The file could not be read. Use the Excel template or a CSV file.')]);
        }

        $map = $this->headerMap(array_shift($rows) ?? []);
        foreach (['code', 'name', 'selling_price'] as $required) {
            if (! isset($map[$required])) {
                throw ValidationException::withMessages(['file' => __('Missing column: :column', ['column' => $required])]);
            }
        }

        $result = ['created' => 0, 'updated' => 0, 'stock' => 0, 'errors' => []];
        foreach ($rows as $i => $row) {
            $line = $i + 2; // spreadsheet row number (header is row 1)
            $get = fn (string $field) => isset($map[$field]) ? trim((string) ($row[$map[$field]] ?? '')) : '';
            if ($get('code') === '' && $get('name') === '') {
                continue; // blank row
            }
            try {
                DB::transaction(function () use ($get, $user, $shopId, &$result) {
                    $product = Product::where('code', $get('code'))->first();
                    $categoryId = $get('category') !== ''
                        ? Category::firstOrCreate(['name' => $get('category')])->id
                        : $product?->category_id;
                    $data = [
                        'code' => $get('code'),
                        'name' => $get('name'),
                        'category_id' => $categoryId,
                        'unit' => $get('unit') ?: ($product->unit ?? 'pcs'),
                        'cost_price' => $this->number($get('cost_price')) ?? ($product->cost_price ?? 0),
                        'selling_price' => $this->number($get('selling_price')),
                        'reorder_level' => $this->number($get('reorder_level')) ?? ($product->reorder_level ?? 0),
                        'is_active' => true,
                    ];
                    $product = $this->catalog->saveProduct($data, $user, $product);
                    $result[$product->wasRecentlyCreated ? 'created' : 'updated']++;

                    $qty = $this->number($get('stock'));
                    if ($shopId && $qty !== null && abs($qty - $this->stock->available($shopId, $product->id)) > 0.0005) {
                        $this->stock->adjust([
                            'shop_id' => $shopId, 'product_id' => $product->id, 'counted_quantity' => $qty,
                            'reason' => __('Opening stock (import)'),
                        ], $user);
                        $result['stock']++;
                    }
                });
            } catch (ValidationException $e) {
                $result['errors'][$line] = collect($e->errors())->flatten()->implode(' ');
            } catch (Throwable $e) {
                $result['errors'][$line] = $e->getMessage();
            }
        }

        return $result;
    }

    /** Excel template with the expected headers and two example rows. */
    public function template(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Products');
        $sheet->fromArray([
            ['code', 'name', 'category', 'unit', 'cost_price', 'selling_price', 'reorder_level', 'stock'],
            ['CEM-50', 'Cement 50kg bag', 'Cement', 'bag', 16500, 19000, 20, 100],
            ['NAIL-3', 'Wire nails 3 inch 1kg', 'Tools', 'kg', 3800, 5000, 10, 50],
        ]);
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2F4F1');
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $book;
    }

    private function headerMap(array $header): array
    {
        $map = [];
        foreach ($header as $index => $title) {
            $key = str_replace([' ', '-'], '_', strtolower(trim((string) $title)));
            foreach (self::COLUMNS as $field => $aliases) {
                if (in_array($key, $aliases, true) && ! isset($map[$field])) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    private function number(string $value): ?float
    {
        $value = str_replace([',', ' '], '', $value);

        return $value === '' || ! is_numeric($value) ? null : (float) $value;
    }
}
