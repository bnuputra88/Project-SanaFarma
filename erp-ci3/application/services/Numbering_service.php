<?php
/**
 * Document numbering engine. Pattern tokens: {PREFIX} {BRANCH} {YYYY} {YY} {MM} {DD} {SEQ:n}.
 * Concurrency-safe: relies on Sequence_repository row lock inside caller's transaction.
 */
class Numbering_service
{
    private $repo;
    private $ctx;
    private $defaults;

    public function __construct(Sequence_repository $repo, Request_context $ctx)
    {
        $this->repo = $repo;
        $this->ctx = $ctx;
        $this->defaults = get_instance()->config->item('erp_numbering_defaults') ?: [];
    }

    public function next(string $docType, ?string $branchCode = null, ?int $branchId = null, ?string $date = null): string
    {
        $def = $this->defaults[$docType] ?? ['pattern' => $docType . '/{YYYY}{MM}/{SEQ:5}', 'reset' => 'monthly'];
        $ts = $date ? strtotime($date) : time();
        $periodKey = $def['reset'] === 'daily' ? date('Ymd', $ts) : ($def['reset'] === 'yearly' ? date('Y', $ts) : ($def['reset'] === 'never' ? '' : date('Ym', $ts)));
        $seq = $this->repo->nextNumber((int) $this->ctx->company_id, $branchId, $docType, $periodKey, $def['pattern']);
        return self::format($seq['pattern'], $seq['number'], ['PREFIX' => $docType, 'BRANCH' => $branchCode ?: 'HO'], $ts);
    }

    public static function format(string $pattern, int $number, array $tokens, int $ts): string
    {
        $out = preg_replace_callback('/\{SEQ:(\d+)\}/', fn($m) => str_pad((string) $number, (int) $m[1], '0', STR_PAD_LEFT), $pattern);
        $map = ['{YYYY}' => date('Y', $ts), '{YY}' => date('y', $ts), '{MM}' => date('m', $ts), '{DD}' => date('d', $ts)];
        foreach ($tokens as $k => $v) {
            $map['{' . $k . '}'] = $v;
        }
        return strtr($out, $map);
    }
}
