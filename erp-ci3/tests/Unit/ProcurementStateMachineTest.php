<?php
use PHPUnit\Framework\TestCase;

/**
 * Unit: state machine procurement (PR/PO/GR/Return). Tidak butuh DB.
 */
final class ProcurementStateMachineTest extends TestCase
{
    private function sm(): State_machine
    {
        $config = [];
        require APP_ROOT . 'application/config/erp.php';
        return new State_machine($config['erp_state_machines']);
    }

    public function testPurchaseOrderHappyPath(): void
    {
        $sm = $this->sm();
        $s = $sm->apply('purchase_order', 'DRAFT', 'submit');
        $this->assertSame('SUBMITTED', $s);
        $s = $sm->apply('purchase_order', $s, 'approve');
        $this->assertSame('APPROVED', $s);
        $s = $sm->apply('purchase_order', $s, 'order');
        $this->assertSame('ORDERED', $s);
        $this->assertSame('CLOSED', $sm->apply('purchase_order', 'RECEIVED', 'close'));
        $this->assertTrue($sm->isFinal('purchase_order', 'CLOSED'));
    }

    public function testPurchaseOrderArbitraryTransitionRejected(): void
    {
        $this->expectException(Invalid_transition_exception::class);
        $this->sm()->apply('purchase_order', 'DRAFT', 'order');
    }

    public function testGoodsReceiptFlowAndReversal(): void
    {
        $sm = $this->sm();
        $s = $sm->apply('goods_receipt', 'DRAFT', 'submit');
        $s = $sm->apply('goods_receipt', $s, 'approve');
        $s = $sm->apply('goods_receipt', $s, 'post');
        $this->assertSame('POSTED', $s);
        $this->assertSame('REVERSED', $sm->apply('goods_receipt', 'POSTED', 'reverse'));
        $this->assertTrue($sm->isFinal('goods_receipt', 'REVERSED'));
    }

    public function testPurchaseReturnAndRequestCannotReopenFinal(): void
    {
        $sm = $this->sm();
        $this->assertSame('POSTED', $sm->apply('purchase_return', 'APPROVED', 'post'));
        $this->assertSame([], $sm->actions('purchase_return', 'POSTED'));
        $this->assertSame([], $sm->actions('purchase_request', 'CLOSED'));
        $this->assertFalse($sm->can('purchase_order', 'CLOSED', 'submit'));
    }
}
