<?php
/** Centralized audit trail. Append-only; captures who/when/action/module/record/old/new/ip/ua/reference/reason. */
class Audit_service
{
    private $repo;
    private $ctx;

    public function __construct(Audit_repository $repo, Request_context $ctx)
    {
        $this->repo = $repo;
        $this->ctx = $ctx;
    }

    public function log(string $module, string $action, string $entity, $entityId, ?array $old = null, ?array $new = null, ?string $referenceNo = null, ?string $reason = null): void
    {
        $this->repo->insert([
            'company_id' => $this->ctx->company_id, 'branch_id' => $this->ctx->branch_id, 'user_id' => $this->ctx->user_id, 'username' => $this->ctx->username,
            'module' => $module, 'action' => $action, 'entity' => $entity, 'entity_id' => $entityId !== null ? (string) $entityId : null, 'reference_no' => $referenceNo,
            'old_values' => $old !== null ? json_encode($this->scrub($old), JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $new !== null ? json_encode($this->scrub($new), JSON_UNESCAPED_UNICODE) : null,
            'reason' => $reason ? mb_substr($reason, 0, 255) : null, 'ip_address' => $this->ctx->ip, 'user_agent' => $this->ctx->user_agent,
            'channel' => $this->ctx->channel, 'request_id' => $this->ctx->request_id, 'created_at' => date('Y-m-d H:i:s.v'),
        ]);
    }

    /** Only store changed keys to keep audit compact and readable. */
    public function diff(array $old, array $new): array
    {
        $o = [];
        $n = [];
        foreach ($new as $k => $v) {
            if (!array_key_exists($k, $old) || (string) $old[$k] !== (string) $v) {
                $o[$k] = $old[$k] ?? null;
                $n[$k] = $v;
            }
        }
        return [$o, $n];
    }

    private function scrub(array $data): array
    {
        foreach (['password', 'password_hash', 'password_confirm', 'mfa_secret', 'token'] as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = '***';
            }
        }
        return $data;
    }
}
