<?php

namespace Tests\Feature;

use App\Services\ReportService;
use App\Services\SaleService;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    public function test_every_report_exports_to_excel_pdf_and_csv(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 10, 16000);
        app(SaleService::class)->create(['shop_id' => $this->main->id, 'payment_method' => 'cash',
            'items' => [['product_id' => $this->p1, 'quantity' => 2, 'unit_price' => 19000]]], $this->shopAdmin);

        $this->actingAs($this->admin);
        foreach (array_keys(ReportService::TYPES) as $type) {
            $this->get("/reports/$type?export=xlsx")->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $pdf = $this->get("/reports/$type?export=pdf")->assertOk();
            $this->assertStringStartsWith('%PDF', $pdf->getContent(), "PDF for $type");
            $this->get("/reports/$type?export=csv")->assertOk();
        }
    }
}
