<?php

namespace Tests\Unit;

use App\Services\BarcodeService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class BarcodeLabelCompatibilityTest extends TestCase
{
    public function test_tilde_label_is_item_code_and_base_qty_without_database_lookup(): void
    {
        DB::shouldReceive('connection')->never();
        $result = app(BarcodeService::class)->lookup(' 00017~5 ');
        $this->assertSame('ITEM_QTY_LABEL', $result->scan_source);
        $this->assertSame('00017', $result->item_code);
        $this->assertSame(5, $result->ratio_qty);
        $this->assertNull($result->uom_code);
    }

    public function test_item_code_may_contain_dash_before_tilde(): void
    {
        DB::shouldReceive('connection')->never();
        $result = app(BarcodeService::class)->lookup('INGCO-ABC~12');
        $this->assertSame('INGCO-ABC', $result->item_code);
        $this->assertSame(12, $result->ratio_qty);
    }

    public function test_without_tilde_lookup_exact_master_barcode_and_ratio(): void
    {
        $this->mockLookups(['899002' => ['ABC', 'BOX', 12]]);
        $result = app(BarcodeService::class)->lookup('899002');
        $this->assertSame('MASTER_BARCODE', $result->scan_source);
        $this->assertSame('ABC', $result->item_code);
        $this->assertSame('BOX', $result->uom_code);
        $this->assertSame(12, $result->ratio_qty);
    }

    public function test_dash_is_not_qty_delimiter_anymore(): void
    {
        $this->mockLookups(['ABC-5' => ['ABC', 'PCS', 1]]);
        $result = app(BarcodeService::class)->lookup('ABC-5');
        $this->assertSame('MASTER_BARCODE', $result->scan_source);
        $this->assertSame(1, $result->ratio_qty);
    }

    public function test_unregistered_plain_barcode_is_not_converted_to_item_code(): void
    {
        $this->mockLookups([]);
        $this->assertNull(app(BarcodeService::class)->lookup('ABC-10'));
    }

    /** @dataProvider invalidPayloads */
    public function test_invalid_tilde_payload_is_rejected(string $barcode): void
    {
        DB::shouldReceive('connection')->never();
        $this->expectException(\RuntimeException::class);
        app(BarcodeService::class)->lookup($barcode);
    }

    public static function invalidPayloads(): array
    {
        return [
            ['ABC~0'], ['ABC~XYZ'], ['~5'], ['ABC~1~2'], ['ABC~-3'],
            ['ABC~2147483648'],
        ];
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
