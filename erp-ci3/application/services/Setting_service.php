<?php
/** Typed access to system_settings with per-request cache. Business rules read from here — never hard-coded. */
class Setting_service
{
    private $repo;
    private $ctx;
    private $audit;
    private $cache;

    public function __construct(Setting_repository $repo, Request_context $ctx, Audit_service $audit)
    {
        $this->repo = $repo;
        $this->ctx = $ctx;
        $this->audit = $audit;
    }

    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = $this->repo->allResolved($this->ctx->company_id);
        }
        return $this->cache;
    }

    public function get(string $key, $default = null)
    {
        $row = $this->all()[$key] ?? null;
        if ($row === null) {
            return $default;
        }
        switch ($row['value_type']) {
            case 'int': return (int) $row['setting_value'];
            case 'decimal': return (float) $row['setting_value'];
            case 'bool': return in_array(strtolower((string) $row['setting_value']), ['1', 'true', 'yes'], true);
            case 'json': return json_decode($row['setting_value'], true) ?? $default;
            default: return $row['setting_value'];
        }
    }

    public function saveMany(array $values): void
    {
        $current = $this->all();
        foreach ($values as $key => $value) {
            if (!isset($current[$key]) || !$current[$key]['is_editable']) {
                continue;
            }
            $old = $current[$key]['setting_value'];
            if ((string) $old === (string) $value) {
                continue;
            }
            $this->repo->upsert($this->ctx->company_id, $key, $value, $this->ctx->user_id);
            $this->audit->log('system', 'update', 'system_settings', $key, ['value' => $old], ['value' => $value]);
        }
        $this->cache = null;
    }

    public function passwordPolicy(): Password_policy
    {
        return new Password_policy([
            'min_length' => $this->get('security.password_min_length', 10), 'require_upper' => $this->get('security.password_require_upper', true),
            'require_lower' => $this->get('security.password_require_lower', true), 'require_digit' => $this->get('security.password_require_digit', true),
            'require_symbol' => $this->get('security.password_require_symbol', true), 'max_age_days' => $this->get('security.password_max_age_days', 90),
        ]);
    }
}
