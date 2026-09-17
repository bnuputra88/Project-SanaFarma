<?php
use PHPUnit\Framework\TestCase;

final class StateMachineTest extends TestCase
{
    private function sm(): State_machine
    {
        $config = [];
        require APP_ROOT . 'application/config/erp.php';
        return new State_machine($config['erp_state_machines']);
    }

    public function testHappyPathAdjustment(): void
    {
        $sm = $this->sm();
        $s = $sm->apply('stock_adjustment', 'DRAFT', 'submit');
        $s = $sm->apply('stock_adjustment', $s, 'approve');
        $s = $sm->apply('stock_adjustment', $s, 'post');
        $this->assertSame('POSTED', $s);
        $this->assertTrue($sm->isFinal('stock_adjustment', 'POSTED'));
    }

    public function testArbitraryTransitionRejected(): void
    {
        $this->expectException(Invalid_transition_exception::class);
        $this->sm()->apply('stock_adjustment', 'DRAFT', 'post');
    }

    public function testCannotReopenFinal(): void
    {
        $this->assertSame([], $this->sm()->actions('stock_transfer', 'RECEIVED'));
        $this->assertFalse($this->sm()->can('stock_opname', 'POSTED', 'submit'));
    }

    public function testTransferFlow(): void
    {
        $sm = $this->sm();
        $this->assertSame('IN_TRANSIT', $sm->apply('stock_transfer', 'APPROVED', 'ship'));
        $this->assertSame('RECEIVED', $sm->apply('stock_transfer', 'IN_TRANSIT', 'receive'));
    }
}
