<?php
/** Shift kasir: OPEN (kas awal) → CLOSED (rekap kas, hitung selisih). Satu kasir hanya boleh 1 shift OPEN. */
class Cashier_shift_service
{
    private $shifts;
    private $numbering;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(Cashier_shift_repository $shifts, Numbering_service $numbering, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->shifts = $shifts;
        $this->numbering = $numbering;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function list(array $input): Paginator
    {
        return $this->shifts->paginate($input, $this->ctx->company_id);
    }

    public function get(int $id): array
    {
        $s = $this->shifts->findOrFail($id);
        if ((int) $s['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        return $s;
    }

    public function currentOpen(): ?array
    {
        return $this->shifts->openForCashier($this->ctx->user_id);
    }

    public function open(array $d): int
    {
        if ($this->shifts->openForCashier($this->ctx->user_id)) {
            throw new Conflict_exception('Anda masih memiliki shift yang terbuka. Tutup dulu sebelum membuka shift baru.');
        }
        $wh = $this->db->ci->select('w.id, w.branch_id, b.code AS branch_code')->from('warehouses w')->join('branches b', 'b.id = w.branch_id')
            ->where(['w.id' => (int) $d['warehouse_id'], 'w.company_id' => $this->ctx->company_id, 'w.is_active' => 1])->get()->row_array();
        if (!$wh) {
            throw new Validation_exception(['warehouse_id' => 'Gudang/etalase tidak valid']);
        }
        return $this->db->transaction(function () use ($d, $wh) {
            $no = $this->numbering->next('CASHIER_SHIFT', $wh['branch_code'], (int) $wh['branch_id']);
            $id = $this->shifts->insert(['company_id' => $this->ctx->company_id, 'branch_id' => (int) $wh['branch_id'], 'warehouse_id' => (int) $wh['id'], 'shift_no' => $no,
                'cashier_id' => $this->ctx->user_id, 'opened_at' => date('Y-m-d H:i:s'), 'opening_cash' => (float) ($d['opening_cash'] ?? 0), 'notes' => $d['notes'] ?: null]);
            $this->audit->log('sales', 'open', 'cashier_shifts', $id, null, ['shift_no' => $no], $no);
            return $id;
        });
    }

    public function close(int $id, array $d): void
    {
        $this->db->transaction(function () use ($id, $d) {
            $s = $this->shifts->findOrFail($id, true);
            if ((int) $s['company_id'] !== $this->ctx->company_id) {
                throw new Not_found_exception();
            }
            if ($s['status'] !== 'OPEN') {
                throw new Invalid_transition_exception('Shift sudah ditutup');
            }
            $t = $this->shifts->totals($id);
            $cash = $this->shifts->cashCollected($id);
            $expected = (float) $s['opening_cash'] + $cash;
            $closing = (float) ($d['closing_cash'] ?? $expected);
            $this->shifts->updateVersioned($id, (int) $s['version'], ['status' => 'CLOSED', 'closed_at' => date('Y-m-d H:i:s'), 'closing_cash' => $closing,
                'expected_cash' => $expected, 'cash_variance' => round($closing - $expected, 2), 'total_sales' => (float) $t['total'], 'sale_count' => (int) $t['cnt'], 'notes' => $d['notes'] ?: $s['notes']]);
            $this->audit->log('sales', 'close', 'cashier_shifts', $id, ['status' => 'OPEN'], ['status' => 'CLOSED', 'variance' => round($closing - $expected, 2)], $s['shift_no']);
        });
    }
}
