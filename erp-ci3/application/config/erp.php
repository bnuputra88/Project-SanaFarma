<?php
defined('BASEPATH') or exit('No direct script access allowed');
/**
 * ERP domain configuration: state machines, document numbering defaults, module registry.
 * Business rules that may change live in system_settings (DB); structural definitions live here.
 */

$config['erp_modules'] = [
    'system' => 'Sistem', 'master' => 'Master Data', 'inventory' => 'Inventori', 'warehouse' => 'Gudang',
    'purchasing' => 'Pembelian', 'sales' => 'Penjualan', 'pharmacy' => 'Farmasi', 'distribution' => 'Distribusi',
    'returns' => 'Retur', 'finance' => 'Keuangan', 'accounting' => 'Akuntansi', 'tax' => 'Pajak',
    'compliance' => 'Kepatuhan', 'quality' => 'Mutu', 'reporting' => 'Laporan', 'audit' => 'Audit',
];

$config['erp_actions'] = ['view', 'create', 'edit', 'delete', 'approve', 'cancel', 'export', 'print', 'authorize', 'post'];

// Explicit document state machines (D. IMPLEMENTATION RULES: no arbitrary status changes from UI)
$config['erp_state_machines'] = [
    'stock_adjustment' => [
        'initial' => 'DRAFT',
        'final' => ['POSTED', 'REJECTED', 'CANCELLED'],
        'transitions' => [
            'DRAFT' => ['submit' => 'SUBMITTED', 'cancel' => 'CANCELLED', 'edit' => 'DRAFT'],
            'SUBMITTED' => ['approve' => 'APPROVED', 'reject' => 'REJECTED', 'cancel' => 'CANCELLED'],
            'APPROVED' => ['post' => 'POSTED', 'cancel' => 'CANCELLED'],
        ],
    ],
    'stock_transfer' => [
        'initial' => 'DRAFT',
        'final' => ['RECEIVED', 'REJECTED', 'CANCELLED'],
        'transitions' => [
            'DRAFT' => ['submit' => 'SUBMITTED', 'cancel' => 'CANCELLED', 'edit' => 'DRAFT'],
            'SUBMITTED' => ['approve' => 'APPROVED', 'reject' => 'REJECTED', 'cancel' => 'CANCELLED'],
            'APPROVED' => ['ship' => 'IN_TRANSIT', 'cancel' => 'CANCELLED'],
            'IN_TRANSIT' => ['receive' => 'RECEIVED'],
        ],
    ],
    'stock_opname' => [
        'initial' => 'DRAFT',
        'final' => ['POSTED', 'CANCELLED'],
        'transitions' => [
            'DRAFT' => ['start' => 'COUNTING', 'cancel' => 'CANCELLED'],
            'COUNTING' => ['submit' => 'SUBMITTED', 'cancel' => 'CANCELLED', 'edit' => 'COUNTING'],
            'SUBMITTED' => ['approve' => 'APPROVED', 'reject' => 'COUNTING'],
            'APPROVED' => ['post' => 'POSTED'],
        ],
    ],
];

// Default numbering patterns; overridable per company in document_sequences.pattern
$config['erp_numbering_defaults'] = [
    'STOCK_ADJ' => ['pattern' => 'ADJ/{BRANCH}/{YYYY}{MM}/{SEQ:5}', 'reset' => 'monthly'],
    'STOCK_TRF' => ['pattern' => 'TRF/{BRANCH}/{YYYY}{MM}/{SEQ:5}', 'reset' => 'monthly'],
    'STOCK_OPN' => ['pattern' => 'OPN/{BRANCH}/{YYYY}{MM}/{SEQ:4}', 'reset' => 'monthly'],
    'STOCK_MOV' => ['pattern' => 'MOV/{YYYY}{MM}{DD}/{SEQ:6}', 'reset' => 'daily'],
    'BATCH_AUTO' => ['pattern' => 'B{YY}{MM}{SEQ:5}', 'reset' => 'monthly'],
];

// Stock movement types: sign tells the engine whether the line adds (+1) or removes (-1) on-hand quantity.
$config['erp_movement_types'] = [
    'OPENING_BALANCE' => ['sign' => 1, 'label' => 'Saldo Awal'],
    'RECEIPT' => ['sign' => 1, 'label' => 'Penerimaan'],
    'ISSUE' => ['sign' => -1, 'label' => 'Pengeluaran'],
    'ADJUSTMENT_IN' => ['sign' => 1, 'label' => 'Penyesuaian (+)'],
    'ADJUSTMENT_OUT' => ['sign' => -1, 'label' => 'Penyesuaian (-)'],
    'TRANSFER_OUT' => ['sign' => -1, 'label' => 'Transfer Keluar'],
    'TRANSFER_IN' => ['sign' => 1, 'label' => 'Transfer Masuk'],
    'OPNAME_IN' => ['sign' => 1, 'label' => 'Opname (+)'],
    'OPNAME_OUT' => ['sign' => -1, 'label' => 'Opname (-)'],
    'QUARANTINE_IN' => ['sign' => 0, 'label' => 'Masuk Karantina'],
    'QUARANTINE_OUT' => ['sign' => 0, 'label' => 'Keluar Karantina'],
    'REVERSAL' => ['sign' => 0, 'label' => 'Pembalikan'],
];

$config['erp_stock_conditions'] = ['GOOD', 'DAMAGED', 'QUARANTINE', 'EXPIRED'];
