<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Output escaping — ALWAYS use in views. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmt_num($n, int $dec = 2): string
{
    return number_format((float) $n, $dec, ',', '.');
}

function fmt_idr($n): string
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function fmt_date(?string $d, string $format = 'd/m/Y'): string
{
    return $d ? date($format, strtotime($d)) : '-';
}

function fmt_dt(?string $d): string
{
    return fmt_date($d, 'd/m/Y H:i');
}

function status_badge(string $status): string
{
    $map = [
        'DRAFT' => 'secondary', 'SUBMITTED' => 'info', 'COUNTING' => 'info', 'APPROVED' => 'primary', 'IN_TRANSIT' => 'warning',
        'POSTED' => 'success', 'RECEIVED' => 'success', 'REJECTED' => 'danger', 'CANCELLED' => 'dark', 'REVERSED' => 'dark',
        'ORDERED' => 'primary', 'PARTIAL' => 'warning', 'CLOSED' => 'dark', 'OPEN' => 'info', 'PAID' => 'success',
        'ACTIVE' => 'success', 'INACTIVE' => 'secondary', 'GOOD' => 'success', 'QUARANTINE' => 'warning', 'DAMAGED' => 'danger', 'EXPIRED' => 'danger',
    ];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge text-bg-' . $cls . ' status-badge" data-testid="status-badge">' . e(lang('status_' . strtolower($status)) ?: $status) . '</span>';
}

/** Permission check available in views for conditional rendering (authorization is still enforced server-side). */
function can(string $permission): bool
{
    $ci = &get_instance();
    return isset($ci->context) && $ci->context->can($permission);
}

function csrf_field(): string
{
    $ci = &get_instance();
    return '<input type="hidden" name="' . $ci->security->get_csrf_token_name() . '" value="' . $ci->security->get_csrf_hash() . '">';
}

function old(string $key, $default = '')
{
    $ci = &get_instance();
    $old = $ci->session->flashdata('_old_input') ?? [];
    return $old[$key] ?? $default;
}

function client_ip(): string
{
    $ci = &get_instance();
    return $ci->input->ip_address();
}

function query_with(array $overrides): string
{
    $q = array_merge($_GET, $overrides);
    return '?' . http_build_query($q);
}

function sort_link(string $col, string $label): string
{
    $cur = $_GET['sort'] ?? '';
    $dir = ($_GET['dir'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
    $icon = $cur === $col ? (($_GET['dir'] ?? 'asc') === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a class="sort-link" href="' . e(query_with(['sort' => $col, 'dir' => $cur === $col ? $dir : 'asc'])) . '">' . e($label) . $icon . '</a>';
}
