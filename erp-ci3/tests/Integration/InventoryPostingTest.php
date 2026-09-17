<?php
use PHPUnit\Framework\TestCase;

/**
 * Integration: real DB (testing env). Covers acceptance scenarios 1,6,8,9,14(reversal),17 for Phase 2.
 * Requires: migrated + seeded database (php index.php cli/migrate latest && php index.php cli/seed run).
 */
final class InventoryPostingTest extends TestCase
{
    private $ci;
    private $inv;
    private $whId;
    private $productId;

    protected function setUp(): void
    {
        $this->ci = ci_boot();
        if (!$this->ci) {
            $this->markTestSkipped('CI/DB not available');
        }
        $ctx = $this->ci->context;
        $admin = $this->ci->db->select('id, company_id, branch_id, username')->from('users')->where('username', 'admin')->get()->row_array();
        $ctx->user_id = (int) $admin['id'];
        $ctx->company_id = (int) $admin['company_id'];
        $ctx->branch_id = (int) $admin['branch_id'];
        $ctx->username = 'admin';
        $ctx->is_superadmin = true;
        $this->inv = $this->ci->container->get(Inventory_service::class);
        $this->whId = (int) $this->ci->db->select('id')->from('warehouses')->where('code', 'WH-HO')->get()->row()->id;
        $this->ci->db->trans_start(); // each test rolls back
        $this->productId = (int) $this->ci->db->insert('products', ['company_id' => $ctx->company_id, 'sku' => 'TEST-' . uniqid(), 'name' => 'Test Obat', 'base_uom_id' => 1, 'created_by' => $ctx->user_id]) ? $this->ci->db->insert_id() : 0;
    }

    protected function tearDown(): void
    {
        if ($this->ci) {
            $this->ci->db->trans_rollback();
        }
    }

    private function onHand(?int $batchId = null): float
    {
        $qb = $this->ci->db->select_sum('qty_on_hand')->from('stock_balances')->where(['product_id' => $this->productId, 'warehouse_id' => $this->whId, 'condition_code' => 'GOOD']);
        if ($batchId) {
            $qb->where('batch_id', $batchId);
        }
        return (float) $qb->get()->row()->qty_on_hand;
    }

    public function testReceiptIncreasesStockAndWritesLedgerAndAudit(): void
    {
        $mid = $this->inv->post(['movement_type' => 'RECEIPT', 'ref_type' => 'test', 'ref_no' => 'T-1'], [
            ['warehouse_id' => $this->whId, 'product_id' => $this->productId, 'batch_no' => 'B1', 'expiry_date' => date('Y-m-d', strtotime('+1 year')), 'qty' => 100, 'unit_cost' => 10],
        ]);
        $this->assertSame(100.0, $this->onHand());
        $ledger = $this->ci->db->from('stock_ledger')->where('movement_id', $mid)->get()->row_array();
        $this->assertEquals(100, $ledger['balance_after']);
        $this->assertSame(1, (int) $this->ci->db->from('audit_logs')->where(['entity' => 'stock_movements', 'entity_id' => (string) $mid])->count_all_results());
    }

    public function testNegativeStockPrevented(): void
    {
        $this->expectException(Insufficient_stock_exception::class);
        $this->inv->post(['movement_type' => 'ISSUE'], [['warehouse_id' => $this->whId, 'product_id' => $this->productId, 'batch_no' => 'B1', 'expiry_date' => date('Y-m-d', strtotime('+1 year')), 'qty' => -1]]);
    }

    public function testFefoChoosesEarliestExpiryAndIssueDecreasesIt(): void
    {
        $late = date('Y-m-d', strtotime('+2 years'));
        $early = date('Y-m-d', strtotime('+3 months'));
        $this->inv->post(['movement_type' => 'RECEIPT'], [
            ['warehouse_id' => $this->whId, 'product_id' => $this->productId, 'batch_no' => 'LATE', 'expiry_date' => $late, 'qty' => 50, 'unit_cost' => 10],
            ['warehouse_id' => $this->whId, 'product_id' => $this->productId, 'batch_no' => 'EARLY', 'expiry_date' => $early, 'qty' => 20, 'unit_cost' => 12],
            ['warehouse_id' => $this->whId, 'product_id' => $this->productId, 'batch_no' => 'EXPIRED', 'expiry_date' => date('Y-m-d', strtotime('-1 day')), 'qty' => 99, 'unit_cost' => 1],
        ]);
        $alloc = $this->inv->allocateFefo($this->productId, $this->whId, 30);
        $this->assertSame(0.0, $alloc['shortage']);
        $this->assertSame(['EARLY', 'LATE'], array_column($alloc['allocations'], 'batch_no'));
        $this->assertEquals([20, 10], array_column($alloc['allocations'], 'qty'));
        $lines = array_map(fn($a) => ['warehouse_id' => $this->whId, 'product_id' => $this->productId, 'batch_id' => $a['batch_id'], 'qty' => -$a['qty']], $alloc['allocations']);
        $this->inv->post(['movement_type' => 'ISSUE', 'ref_no' => 'FEFO-TEST'], $lines);
        $this->assertSame(0.0, $this->onHand($alloc['allocations'][0]['batch_id']));
        $this->assertSame(40.0, $this->onHand($alloc['allocations'][1]['batch_id']));
    }

    public function testReversalRestoresStockAndKeepsOriginalImmutable(): void
    {
        $mid = $this->inv->post(['movement_type' => 'RECEIPT'], [['warehouse_id' => $this->whId, 'product_id' => $this->productId, 'batch_no' => 'R1', 'expiry_date' => date('Y-m-d', strtotime('+1 year')), 'qty' => 10, 'unit_cost' => 5]]);
        $rev = $this->inv->reverse($mid, 'salah input');
        $this->assertSame(0.0, $this->onHand());
        $orig = $this->ci->db->from('stock_movements')->where('id', $mid)->get()->row_array();
        $this->assertSame('REVERSED', $orig['status']);
        $this->assertSame((string) $rev, (string) $orig['reversed_by_id']);
        $this->assertSame(2, (int) $this->ci->db->from('stock_ledger')->where('product_id', $this->productId)->count_all_results(), 'ledger append-only: 2 rows');
        $this->expectException(Conflict_exception::class);
        $this->inv->reverse($mid, 'double reversal must fail');
    }

    public function testDocumentNumberingIsUniqueUnderRepeatedCalls(): void
    {
        $n = $this->ci->container->get(Numbering_service::class);
        $a = $n->next('STOCK_ADJ', 'HO', $this->ci->context->branch_id);
        $b = $n->next('STOCK_ADJ', 'HO', $this->ci->context->branch_id);
        $this->assertNotSame($a, $b);
        $this->assertSame(1, (int) substr($b, -5) - (int) substr($a, -5));
    }
}
