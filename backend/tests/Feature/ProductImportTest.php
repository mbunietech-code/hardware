<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\ProductImportService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    public function test_csv_import_creates_updates_sets_stock_and_reports_bad_rows(): void
    {
        $csv = "code,name,category,unit,cost_price,selling_price,reorder_level,stock\n"
            ."CEM-50,Cement 50kg bag,Cement,bag,17000,20000,20,75\n"     // existing → update
            ."GLUE-1,Wood glue 1L,Adhesives,bottle,6000,8000,5,12\n"   // new
            ."BAD-1,No price,,,,,,\n";                                // missing selling price
        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($this->admin)
            ->post('/products/import', ['file' => $file, 'shop_id' => $this->main->id])
            ->assertRedirect('/products/import')
            ->assertSessionHas('import', fn ($r) => $r['created'] === 1 && $r['updated'] === 1 && $r['stock'] === 2 && isset($r['errors'][4]));

        $this->assertEquals(20000, Product::where('code', 'CEM-50')->value('selling_price'));
        $glue = Product::where('code', 'GLUE-1')->first();
        $this->assertSame('Adhesives', $glue->category->name);
        $this->assertSame(12.0, $this->qty($glue->id));
        $this->assertSame(75.0, $this->qty($this->p1));
        $this->assertDatabaseHas('stock_adjustments', ['product_id' => $glue->id, 'after_qty' => 12]);
    }

    public function test_template_download_and_permission(): void
    {
        $this->actingAs($this->admin)->get('/products/import/template')->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($this->shopAdmin)->get('/products/import')->assertForbidden();
        $this->assertNotEmpty(app(ProductImportService::class)->template()->getActiveSheet()->getCell('A2')->getValue());
    }
}
