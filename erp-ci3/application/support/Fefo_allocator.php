<?php
/**
 * Pure FEFO (First-Expired-First-Out) allocation over batch balance rows.
 * Deterministic and side-effect free so it can be unit-tested without a database.
 *
 * Each candidate row: ['batch_id'=>int, 'expiry_date'=>'Y-m-d'|null, 'available'=>float, 'location_id'=>int|null, 'unit_cost'=>float]
 */
final class Fefo_allocator
{
    /**
     * @param array $candidates balance rows (any order)
     * @param float $qty requested quantity
     * @param string|null $today reference date; batches with expiry < today are skipped unless $allowExpired
     * @param bool $allowExpired
     * @return array ['allocations'=>[['batch_id','location_id','qty','unit_cost','expiry_date']...], 'shortage'=>float]
     */
    public static function allocate(array $candidates, float $qty, ?string $today = null, bool $allowExpired = false): array
    {
        $today = $today ?: date('Y-m-d');
        $eligible = array_values(array_filter($candidates, function ($c) use ($today, $allowExpired) {
            if ((float) ($c['available'] ?? 0) <= 0) {
                return false;
            }
            if (!$allowExpired && !empty($c['expiry_date']) && $c['expiry_date'] < $today) {
                return false;
            }
            return true;
        }));

        usort($eligible, function ($a, $b) {
            // earliest expiry first; NULL expiry (non-perishable) last; then oldest batch id
            $ea = $a['expiry_date'] ?? '9999-12-31';
            $eb = $b['expiry_date'] ?? '9999-12-31';
            if ($ea !== $eb) {
                return strcmp($ea, $eb);
            }
            return ((int) $a['batch_id']) <=> ((int) $b['batch_id']);
        });

        $remaining = $qty;
        $allocations = [];
        foreach ($eligible as $c) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (float) $c['available']);
            $allocations[] = [
                'batch_id' => (int) $c['batch_id'],
                'location_id' => isset($c['location_id']) ? (int) $c['location_id'] : null,
                'qty' => $take,
                'unit_cost' => (float) ($c['unit_cost'] ?? 0),
                'expiry_date' => $c['expiry_date'] ?? null,
            ];
            $remaining -= $take;
        }
        return ['allocations' => $allocations, 'shortage' => (float) max(0.0, $remaining)];
    }
}
