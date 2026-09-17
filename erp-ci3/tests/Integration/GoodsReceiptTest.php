<?php
use PHPUnit\Framework\TestCase;

/**
 * Integration (butuh DB testing yang sudah dimigrasi + seed): GR posting menaikkan stok,
 * menulis ledger append-only, sinkron ke PO, dan pembalikan ditolak bila stok sudah terpakai.
 * Jalankan: docker compose exec app vendor/bin/phpunit --testsuite Integration
 */
final class GoodsReceiptTest extends TestCase
{
    private $ci;

    protected function setUp(): void
    {
        $this->ci = ci_boot();
        if (!$this->ci) {
            $this->markTestSkipped('CI/DB not available');
        }
        $admin = $this->ci->db->select('id, company_id, branch_id')->from('users')->where('username', 'admin')->get()->row_array();
        $ctx = $this->ci->context;
        $ctx->user_id = (int) $admin['id'];
        $ctx->company_id = (int) $admin['company_id'];
        $ctx->branch_id = (int) $admin['branch_id'];
        $ctx->username = 'admin';
        $ctx->is_superadmin = true;
        $this->ci->db->trans_start();
    }

    protected function tearDown(): void
    {
        if ($this->ci) {
            $this->ci->db->trans_rollback();
        }
    }

    private function ids(): array
    {
        $wh = (int) $this->ci->db->select('id')->from('warehouses')->where('code', 'WH-HO')->get()->row()->id;
        $sup = (int) $this->ci->db->select('id')->from('suppliers')->where('code', 'SUP-APL')->get()->row()->id;
        $prod = (int) $this->ci->db->select('id')->from('products')->where('sku', 'PCT-500-TAB')->get()->row()->id;
        return [$wh, $sup, $prod];
    }

    private function onHand(int $whId, int $productId): float
    {
        return (float) $this->ci->db->select_sum('qty_on_hand')->from('stock_balances')
            ->where(['warehouse_id' => $whId, 'product_id' => $productId, 'condition_code' => 'GOOD'])->get()->row()->qty_on_hand;
    }

    public function testGrPostingIncreasesStockAndWritesLedgerAndPrice(): void
    {
        [$wh, $sup, $prod] = $this->ids();
        $svc = $this->ci->container->get(Goods_receipt_service::class);
        $before = $this->onHand($wh, $prod);
        $grId = $svc->create(['supplier_id' => $sup, 'warehouse_id' => $wh, 'receipt_date' => date('Y-m-d'), 'supplier_do_no' => 'DO-TEST-1',
            'items' => [['product_id' => $prod, 'batch_no' => 'GRTEST-' . uniqid(), 'expiry_date' => date('Y-m-d', strtotime('+1 year')), 'qty_received' => 100, 'unit_cost' => 260, 'inspection_result' => 'ACCEPTED']]]);
        $svc->action($grId, 'submit');
        $svc->action($grId, 'approve');
        $status = $svc->action($grId, 'post');
        $this->assertSame('POSTED', $status);
        $this->assertSame($before + 100.0, $this->onHand($wh, $prod));
        $gr = $this->ci->db->from('goods_receipts')->where('id', $grId)->get()->row_array();
        $this->assertNotNull($gr['movement_id']);
        $ledger = (int) $this->ci->db->from('stock_ledger')->where('movement_id', (int) $gr['movement_id'])->count_all_results();
        $this->assertGreaterThan(0, $ledger);
        $price = (int) $this->ci->db->from('supplier_price_history')->where(['supplier_id' => $sup, 'product_id' => $prod, 'source_ref_id' => $grId])->count_all_results();
        $this->assertSame(1, $price, 'harga supplier tercatat di riwayat');
    }

    public function testPharmaProductRequiresBatchAtPost(): void
    {
        [$wh, $sup, $prod] = $this->ids();
        $svc = $this->ci->container->get(Goods_receipt_service::class);
        // batch_no dikosongkan untuk produk batch-tracked → ditolak di service layer saat posting
        $grId = $svc->create(['supplier_id' => $sup, 'warehouse_id' => $wh, 'receipt_date' => date('Y-m-d'),
            'items' => [['product_id' => $prod, 'batch_no' => '', 'expiry_date' => '', 'qty_received' => 10, 'unit_cost' => 260, 'inspection_result' => 'ACCEPTED']]]);
        $svc->action($grId, 'submit');
        $svc->action($grId, 'approve');
        $this->expectException(Validation_exception::class);
        $svc->action($grId, 'post');
    }

    public function testReversalRejectedWhenStockConsumed(): void
    {
        [$wh, $sup, $prod] = $this->ids();
        $svc = $this->ci->container->get(Goods_receipt_service::class);
        $inv = $this->ci->container->get(Inventory_service::class);
        $batch = 'GRREV-' . uniqid();
        $grId = $svc->create(['supplier_id' => $sup, 'warehouse_id' => $wh, 'receipt_date' => date('Y-m-d'),
            'items' => [['product_id' => $prod, 'batch_no' => $batch, 'expiry_date' => date('Y-m-d', strtotime('+1 year')), 'qty_received' => 20, 'unit_cost' => 260, 'inspection_result' => 'ACCEPTED']]]);
        $svc->action($grId, 'submit');
        $svc->action($grId, 'approve');
        $svc->action($grId, 'post');
        $batchId = (int) $this->ci->db->select('id')->from('batches')->where(['product_id' => $prod, 'batch_no' => $batch])->get()->row()->id;
        // konsumsi seluruh stok batch → reversal harus gagal dengan Conflict
        $inv->post(['movement_type' => 'ISSUE', 'ref_no' => 'CONSUME'], [['warehouse_id' => $wh, 'product_id' => $prod, 'batch_id' => $batchId, 'qty' => -20]]);
        $this->expectException(Conflict_exception::class);
        $svc->action($grId, 'reverse', 'coba balik padahal sudah terpakai');
    }
}
