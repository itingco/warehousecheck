<?php

namespace Tests\Unit;

use App\Services\BarcodeService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class BarcodeLabelCompatibilityTest extends TestCase
{
    public function test_master_barcode_with_dash_wins_over_label_parser(): void
    {
        $this->mockLookups(['ABC-5' => ['ABC', 'PCS', 1]]);
        $result = app(BarcodeService::class)->lookup('ABC-5');
        $this->assertSame('MASTER_BARCODE', $result->scan_source);
        $this->assertSame(1, $result->ratio_qty);
    }

    public function test_box_label_multiplies_unit_ratio(): void
    {
        $this->mockLookups(['899002' => ['ABC', 'BOX', 12]]);
        $result = app(BarcodeService::class)->lookup('899002-5');
        $this->assertSame('BARCODE_QTY_LABEL', $result->scan_source);
        $this->assertSame(60, $result->ratio_qty);
        $this->assertSame('ABC', $result->item_code);
    }

    public function test_item_code_label_is_base_quantity(): void
    {
        $this->mockLookups([]);
        $result = app(BarcodeService::class)->lookup('ABC-10');
        $this->assertSame('ITEM_QTY_LABEL', $result->scan_source);
        $this->assertSame(10, $result->ratio_qty);
    }

    public function test_missing_box_ratio_does_not_silently_count_pcs(): void
    {
        $this->mockLookups(['899002' => ['ABC', 'BOX', null]]);
        $result = app(BarcodeService::class)->lookup('899002-5');
        $this->assertSame('BARCODE_QTY_LABEL_MISSING_RATIO', $result->scan_source);
        $this->assertNull($result->ratio_qty);
    }

    public function test_invalid_suffix_does_not_scan(): void
    {
        $this->mockLookups([]);
        $this->assertNull(app(BarcodeService::class)->lookup('ABC-XYZ'));
    }

    private function mockLookups(array $values): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('selectOne')->andReturnUsing(static function (string $sql, array $params) use ($values): ?object {
            if (!isset($values[$params[0]])) return null;
            [$item, $uom, $ratio] = $values[$params[0]];
            return (object) ['barcode' => $params[0], 'item_code' => $item, 'uom_code' => $uom, 'ratio_qty' => $ratio];
        });
        DB::shouldReceive('connection')->with('apphub')->andReturn($connection);
    }
}
