<?php
defined('BASEPATH') or exit('No direct script access allowed');

$route['default_controller'] = 'dashboard';
$route['404_override'] = 'errors/not_found';
$route['translate_uri_dashes'] = true;

// Auth
$route['login'] = 'auth/login';
$route['logout'] = 'auth/logout';
$route['password/forgot'] = 'auth/forgot';
$route['password/reset/(:any)'] = 'auth/reset/$1';
$route['profile/password'] = 'auth/change_password';

// System
$route['system/users'] = 'users/index';
$route['system/users/create'] = 'users/create';
$route['system/users/(:num)/edit'] = 'users/edit/$1';
$route['system/users/(:num)/toggle'] = 'users/toggle/$1';
$route['system/roles'] = 'roles/index';
$route['system/roles/create'] = 'roles/create';
$route['system/roles/(:num)/edit'] = 'roles/edit/$1';
$route['system/settings'] = 'settings/index';
$route['system/settings/save'] = 'settings/save';
$route['system/login-history'] = 'audit_logs/login_history';
$route['audit'] = 'audit_logs/index';
$route['audit/(:num)'] = 'audit_logs/show/$1';

// Master
$route['master/products'] = 'products/index';
$route['master/products/create'] = 'products/create';
$route['master/products/(:num)'] = 'products/show/$1';
$route['master/products/(:num)/edit'] = 'products/edit/$1';
$route['master/products/(:num)/toggle'] = 'products/toggle/$1';
$route['master/products/search'] = 'products/search';
$route['master/categories'] = 'product_categories/index';
$route['master/categories/save'] = 'product_categories/save';
$route['master/uoms'] = 'uoms/index';
$route['master/uoms/save'] = 'uoms/save';
$route['master/warehouses'] = 'warehouses/index';
$route['master/warehouses/save'] = 'warehouses/save';
$route['master/warehouses/(:num)/locations'] = 'warehouses/locations/$1';
$route['master/warehouses/(:num)/locations/save'] = 'warehouses/save_location/$1';
$route['master/reason-codes'] = 'reason_codes/index';
$route['master/reason-codes/save'] = 'reason_codes/save';

// Inventory
$route['inventory/stock'] = 'stock/balances';
$route['inventory/stock/card/(:num)'] = 'stock/card/$1';
$route['inventory/stock/ledger'] = 'stock/ledger';
$route['inventory/stock/expiry'] = 'stock/expiry';
$route['inventory/stock/fefo'] = 'stock/fefo';
$route['inventory/movements'] = 'stock/movements';
$route['inventory/movements/(:num)'] = 'stock/movement/$1';
$route['inventory/movements/(:num)/reverse'] = 'stock/reverse/$1';
$route['inventory/batches'] = 'batches/index';
$route['inventory/batches/(:num)/quarantine'] = 'batches/quarantine/$1';
$route['inventory/batches/(:num)/release'] = 'batches/release/$1';
$route['inventory/adjustments'] = 'stock_adjustments/index';
$route['inventory/adjustments/create'] = 'stock_adjustments/create';
$route['inventory/adjustments/(:num)'] = 'stock_adjustments/show/$1';
$route['inventory/adjustments/(:num)/edit'] = 'stock_adjustments/edit/$1';
$route['inventory/adjustments/(:num)/action/(:any)'] = 'stock_adjustments/action/$1/$2';
$route['inventory/transfers'] = 'stock_transfers/index';
$route['inventory/transfers/create'] = 'stock_transfers/create';
$route['inventory/transfers/(:num)'] = 'stock_transfers/show/$1';
$route['inventory/transfers/(:num)/action/(:any)'] = 'stock_transfers/action/$1/$2';
$route['inventory/opnames'] = 'stock_opnames/index';
$route['inventory/opnames/create'] = 'stock_opnames/create';
$route['inventory/opnames/(:num)'] = 'stock_opnames/show/$1';
$route['inventory/opnames/(:num)/count'] = 'stock_opnames/count/$1';
$route['inventory/opnames/(:num)/action/(:any)'] = 'stock_opnames/action/$1/$2';

// Purchasing (Phase 3)
$route['purchasing'] = 'purchasing/dashboard';
$route['purchasing/suppliers'] = 'suppliers/index';
$route['purchasing/suppliers/create'] = 'suppliers/create';
$route['purchasing/suppliers/(:num)'] = 'suppliers/show/$1';
$route['purchasing/suppliers/(:num)/edit'] = 'suppliers/edit/$1';
$route['purchasing/requests'] = 'purchase_requests/index';
$route['purchasing/requests/create'] = 'purchase_requests/create';
$route['purchasing/requests/(:num)'] = 'purchase_requests/show/$1';
$route['purchasing/requests/(:num)/edit'] = 'purchase_requests/edit/$1';
$route['purchasing/requests/(:num)/action/(:any)'] = 'purchase_requests/action/$1/$2';
$route['purchasing/orders'] = 'purchase_orders/index';
$route['purchasing/orders/create'] = 'purchase_orders/create';
$route['purchasing/orders/(:num)'] = 'purchase_orders/show/$1';
$route['purchasing/orders/(:num)/edit'] = 'purchase_orders/edit/$1';
$route['purchasing/orders/(:num)/action/(:any)'] = 'purchase_orders/action/$1/$2';
$route['purchasing/receipts'] = 'goods_receipts/index';
$route['purchasing/receipts/create'] = 'goods_receipts/create';
$route['purchasing/receipts/(:num)'] = 'goods_receipts/show/$1';
$route['purchasing/receipts/(:num)/edit'] = 'goods_receipts/edit/$1';
$route['purchasing/receipts/(:num)/action/(:any)'] = 'goods_receipts/action/$1/$2';
$route['purchasing/returns'] = 'purchase_returns/index';
$route['purchasing/returns/create'] = 'purchase_returns/create';
$route['purchasing/returns/(:num)'] = 'purchase_returns/show/$1';
$route['purchasing/returns/(:num)/edit'] = 'purchase_returns/edit/$1';
$route['purchasing/returns/(:num)/action/(:any)'] = 'purchase_returns/action/$1/$2';
$route['purchasing/ap-invoices'] = 'ap_invoices/index';
$route['purchasing/ap-invoices/(:num)'] = 'ap_invoices/show/$1';
$route['purchasing/ap-invoices/from-gr/(:num)'] = 'ap_invoices/create_from_gr/$1';
$route['purchasing/ap-invoices/(:num)/cancel'] = 'ap_invoices/cancel/$1';

// Penjualan / POS
$route['sales/pos'] = 'sales/index';
$route['sales/pos/create'] = 'sales/create';
$route['sales/pos/(:num)'] = 'sales/show/$1';
$route['sales/pos/(:num)/void'] = 'sales/void/$1';
$route['sales/customers'] = 'customers/index';
$route['sales/customers/create'] = 'customers/create';
$route['sales/customers/search'] = 'customers/search';
$route['sales/customers/(:num)/edit'] = 'customers/edit/$1';
$route['sales/shifts'] = 'cashier_shifts/index';
$route['sales/shifts/open'] = 'cashier_shifts/open';
$route['sales/shifts/(:num)/close'] = 'cashier_shifts/close/$1';
$route['sales/shifts/(:num)'] = 'cashier_shifts/show/$1';
$route['sales/returns'] = 'sales_returns/index';
$route['sales/returns/create'] = 'sales_returns/create';
$route['sales/returns/(:num)'] = 'sales_returns/show/$1';
$route['sales/returns/(:num)/action/(:any)'] = 'sales_returns/action/$1/$2';

// REST API v1
$route['api/v1/health'] = 'api/v1/health/index';
$route['api/v1/auth/login'] = 'api/v1/auth/login';
$route['api/v1/auth/refresh'] = 'api/v1/auth/refresh';
$route['api/v1/auth/me'] = 'api/v1/auth/me';
$route['api/v1/products'] = 'api/v1/products/index';
$route['api/v1/products/(:num)'] = 'api/v1/products/show/$1';
$route['api/v1/stock/balances'] = 'api/v1/stock/balances';
$route['api/v1/stock/fefo'] = 'api/v1/stock/fefo';
$route['api/v1/stock/ledger'] = 'api/v1/stock/ledger';
$route['api/v1/stock/adjustments'] = 'api/v1/stock/adjustments';
$route['api/v1/stock/adjustments/(:num)/(:any)'] = 'api/v1/stock/adjustment_action/$1/$2';
$route['api/v1/suppliers'] = 'api/v1/suppliers/index';
$route['api/v1/suppliers/create'] = 'api/v1/suppliers/store';
$route['api/v1/suppliers/(:num)'] = 'api/v1/suppliers/show/$1';
$route['api/v1/suppliers/(:num)/price-history'] = 'api/v1/suppliers/price_history/$1';
$route['api/v1/purchase-orders'] = 'api/v1/purchase_orders/index';
$route['api/v1/purchase-orders/(:num)'] = 'api/v1/purchase_orders/show/$1';
$route['api/v1/purchase-orders/(:num)/(:any)'] = 'api/v1/purchase_orders/action/$1/$2';
$route['api/v1/goods-receipts'] = 'api/v1/goods_receipts/index';
$route['api/v1/goods-receipts/(:num)'] = 'api/v1/goods_receipts/show/$1';
$route['api/v1/goods-receipts/(:num)/(:any)'] = 'api/v1/goods_receipts/action/$1/$2';
$route['api/v1/purchase-requests'] = 'api/v1/purchase_requests/index';
$route['api/v1/purchase-requests/(:num)'] = 'api/v1/purchase_requests/show/$1';
$route['api/v1/purchase-requests/(:num)/(:any)'] = 'api/v1/purchase_requests/action/$1/$2';
$route['api/v1/purchase-returns'] = 'api/v1/purchase_returns/index';
$route['api/v1/purchase-returns/(:num)'] = 'api/v1/purchase_returns/show/$1';
$route['api/v1/purchase-returns/(:num)/(:any)'] = 'api/v1/purchase_returns/action/$1/$2';
$route['api/v1/purchase-orders/from-pr/(:num)'] = 'api/v1/purchase_orders/from_pr/$1';

// CLI
$route['cli/migrate/(:any)'] = 'cli/migrate/index/$1';
$route['cli/migrate/(:any)/(:num)'] = 'cli/migrate/index/$1/$2';
$route['cli/seed/(:any)'] = 'cli/seed/index/$1';
$route['cli/health'] = 'cli/health/index';
$route['cli/noop'] = 'cli/noop/index';
