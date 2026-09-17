<?php
use PHPUnit\Framework\TestCase;

final class FefoAllocatorTest extends TestCase
{
    private function rows(): array
    {
        return [
            ['batch_id' => 3, 'expiry_date' => '2027-12-31', 'available' => 100, 'unit_cost' => 10],
            ['batch_id' => 1, 'expiry_date' => '2026-09-30', 'available' => 30, 'unit_cost' => 12],
            ['batch_id' => 2, 'expiry_date' => '2026-01-01', 'available' => 50, 'unit_cost' => 11], // expired relative to today below
            ['batch_id' => 4, 'expiry_date' => null, 'available' => 500, 'unit_cost' => 9],
            ['batch_id' => 5, 'expiry_date' => '2026-09-30', 'available' => 0, 'unit_cost' => 12],
        ];
    }

    public function testPicksEarliestExpiryFirstAndSkipsExpired(): void
    {
        $r = Fefo_allocator::allocate($this->rows(), 40, '2026-06-15');
        $this->assertSame(0.0, $r['shortage']);
        $this->assertSame([1, 3], array_column($r['allocations'], 'batch_id'));
        $this->assertEquals([30, 10], array_column($r['allocations'], 'qty'));
    }

    public function testNullExpiryIsUsedLast(): void
    {
        $r = Fefo_allocator::allocate($this->rows(), 140, '2026-06-15');
        $this->assertSame([1, 3, 4], array_column($r['allocations'], 'batch_id'));
        $this->assertEquals(10, end($r['allocations'])['qty']);
    }

    public function testReportsShortage(): void
    {
        $r = Fefo_allocator::allocate([['batch_id' => 1, 'expiry_date' => '2027-01-01', 'available' => 5]], 8, '2026-06-15');
        $this->assertSame(3.0, $r['shortage']);
    }

    public function testAllowExpiredFlag(): void
    {
        $r = Fefo_allocator::allocate($this->rows(), 10, '2026-06-15', true);
        $this->assertSame(2, $r['allocations'][0]['batch_id']);
    }

    public function testDeterministicTieBreakByBatchId(): void
    {
        $rows = [['batch_id' => 9, 'expiry_date' => '2027-01-01', 'available' => 10], ['batch_id' => 7, 'expiry_date' => '2027-01-01', 'available' => 10]];
        $r = Fefo_allocator::allocate($rows, 15, '2026-06-15');
        $this->assertSame([7, 9], array_column($r['allocations'], 'batch_id'));
    }
}
