<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Primary key semua tabel adalah UUIDv7
$routes->addPlaceholder('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}');

// Publik
$routes->get('/', 'HomeController::index', ['as' => 'home']);

// Tiket tanpa login (publik)
$routes->get('tiket/baru', 'PublicTicketController::new', ['as' => 'public.tickets.new']);
// Dibatasi 5 pengajuan per 10 menit per IP: tiap pengajuan memanggil API kepegawaian
$routes->post('tiket', 'PublicTicketController::create', ['as' => 'public.tickets.create', 'filter' => 'throttle:5,600']);

// Tamu saja
$routes->group('', ['filter' => 'guest'], static function (RouteCollection $routes) {
    $routes->get('login', 'AuthController::showLogin', ['as' => 'login']);
    $routes->post('login', 'AuthController::login', ['as' => 'login.attempt']);
});

$routes->post('logout', 'AuthController::logout', ['as' => 'logout', 'filter' => 'auth']);

// Area user: /u
$routes->group('u', ['namespace' => 'App\Controllers\User', 'filter' => 'auth:user.dashboard.view'], static function (RouteCollection $routes) {
    $routes->get('/', 'DashboardController::index', ['as' => 'user.dashboard']);

    // Tiket: daftar tiket yang terlihat oleh user, dan form buat tiket
    $routes->get('tickets', 'TicketController::index', ['as' => 'user.tickets.index', 'filter' => 'auth:user.tickets.view']);
    $routes->get('tickets/new', 'TicketController::new', ['as' => 'user.tickets.new', 'filter' => 'auth:user.tickets.create']);
    $routes->get('tickets/(:uuid)', 'TicketController::show/$1', ['as' => 'user.tickets.show', 'filter' => 'auth:user.tickets.view']);
    $routes->post('tickets', 'TicketController::create', ['as' => 'user.tickets.create', 'filter' => 'auth:user.tickets.create']);
});

// Area admin: /admin. Gerbang area = admin.access, tiap halaman menambah permission-nya sendiri.
$routes->group('admin', ['namespace' => 'App\Controllers\Admin', 'filter' => 'auth:admin.access'], static function (RouteCollection $routes) {
    $routes->get('/', 'DashboardController::index', ['as' => 'admin.dashboard', 'filter' => 'auth:admin.dashboard.view']);

    // Halaman kerangka: cukup gerbang admin.access. Tambah permission per halaman saat fiturnya dibuat.
    $routes->get('tickets', 'TicketController::index', ['as' => 'admin.tickets.index']);
    $routes->get('tickets/mine', 'TicketController::mine', ['as' => 'admin.tickets.mine']);
    $routes->get('tickets/(:uuid)', 'TicketController::show/$1', ['as' => 'admin.tickets.show']);
    $routes->post('tickets/(:uuid)/status', 'TicketController::updateStatus/$1', ['as' => 'admin.tickets.status', 'filter' => 'auth:admin.tickets.update']);
    $routes->post('tickets/(:uuid)/assign', 'TicketController::assign/$1', ['as' => 'admin.tickets.assign', 'filter' => 'auth:admin.tickets.assign']);
    $routes->get('faqs', 'FaqController::index', ['as' => 'admin.faqs.index']);
    $routes->get('forms', 'FormController::index', ['as' => 'admin.forms.index']);
    $routes->get('reports', 'ReportController::index', ['as' => 'admin.reports.index']);

    // CRUD: tiap aksi dijaga permission-nya sendiri (view, create, update, delete)

    // Roles Category: role dan permission-nya
    $routes->get('roles', 'RoleController::index', ['as' => 'admin.roles.index', 'filter' => 'auth:admin.roles.view']);
    $routes->get('roles/new', 'RoleController::new', ['as' => 'admin.roles.new', 'filter' => 'auth:admin.roles.create']);
    $routes->post('roles', 'RoleController::create', ['as' => 'admin.roles.create', 'filter' => 'auth:admin.roles.create']);
    $routes->get('roles/(:uuid)/edit', 'RoleController::edit/$1', ['as' => 'admin.roles.edit', 'filter' => 'auth:admin.roles.update']);
    $routes->post('roles/(:uuid)', 'RoleController::update/$1', ['as' => 'admin.roles.update', 'filter' => 'auth:admin.roles.update']);
    $routes->post('roles/(:uuid)/delete', 'RoleController::delete/$1', ['as' => 'admin.roles.delete', 'filter' => 'auth:admin.roles.delete']);

    // User Management: daftar pengguna dan penggantian role
    $routes->get('users', 'UserController::index', ['as' => 'admin.users.index', 'filter' => 'auth:admin.users.view']);
    $routes->post('users/(:uuid)/role', 'UserController::updateRole/$1', ['as' => 'admin.users.role', 'filter' => 'auth:admin.users.update']);
    $routes->get('users/(:uuid)/category-access', 'UserCategoryAccessController::show/$1', ['as' => 'admin.users.category-access', 'filter' => 'auth:admin.users.category-access']);
    $routes->post('users/(:uuid)/category-access', 'UserCategoryAccessController::store/$1', ['as' => 'admin.users.category-access.store', 'filter' => 'auth:admin.users.category-access']);
    $routes->post('users/(:uuid)/category-access/(:uuid)/delete', 'UserCategoryAccessController::delete/$1/$2', ['as' => 'admin.users.category-access.delete', 'filter' => 'auth:admin.users.category-access']);

    // SLA Policies
    $routes->get('sla-policies', 'SlaPolicyController::index', ['as' => 'admin.sla-policies.index', 'filter' => 'auth:admin.sla-policies.view']);
    $routes->get('sla-policies/new', 'SlaPolicyController::new', ['as' => 'admin.sla-policies.new', 'filter' => 'auth:admin.sla-policies.create']);
    $routes->post('sla-policies', 'SlaPolicyController::create', ['as' => 'admin.sla-policies.create', 'filter' => 'auth:admin.sla-policies.create']);
    $routes->get('sla-policies/(:uuid)/edit', 'SlaPolicyController::edit/$1', ['as' => 'admin.sla-policies.edit', 'filter' => 'auth:admin.sla-policies.update']);
    $routes->post('sla-policies/(:uuid)', 'SlaPolicyController::update/$1', ['as' => 'admin.sla-policies.update', 'filter' => 'auth:admin.sla-policies.update']);
    $routes->post('sla-policies/(:uuid)/delete', 'SlaPolicyController::delete/$1', ['as' => 'admin.sla-policies.delete', 'filter' => 'auth:admin.sla-policies.delete']);

    // Request Category: kategori layanan dan job title yang boleh melihatnya
    $routes->get('request-categories', 'RequestCategoryController::index', ['as' => 'admin.request-categories.index', 'filter' => 'auth:admin.request-categories.view']);
    $routes->get('request-categories/new', 'RequestCategoryController::new', ['as' => 'admin.request-categories.new', 'filter' => 'auth:admin.request-categories.create']);
    $routes->post('request-categories', 'RequestCategoryController::create', ['as' => 'admin.request-categories.create', 'filter' => 'auth:admin.request-categories.create']);
    $routes->get('request-categories/(:uuid)/edit', 'RequestCategoryController::edit/$1', ['as' => 'admin.request-categories.edit', 'filter' => 'auth:admin.request-categories.update']);
    $routes->post('request-categories/(:uuid)', 'RequestCategoryController::update/$1', ['as' => 'admin.request-categories.update', 'filter' => 'auth:admin.request-categories.update']);
    $routes->post('request-categories/(:uuid)/delete', 'RequestCategoryController::delete/$1', ['as' => 'admin.request-categories.delete', 'filter' => 'auth:admin.request-categories.delete']);

    // Holiday: hari libur (CRUD + sync dari API eksternal) dan hari off mingguan
    $routes->get('holidays', 'HolidayController::index', ['as' => 'admin.holidays.index', 'filter' => 'auth:admin.holidays.view']);
    $routes->get('holidays/new', 'HolidayController::new', ['as' => 'admin.holidays.new', 'filter' => 'auth:admin.holidays.create']);
    $routes->post('holidays', 'HolidayController::create', ['as' => 'admin.holidays.create', 'filter' => 'auth:admin.holidays.create']);
    $routes->post('holidays/sync', 'HolidayController::sync', ['as' => 'admin.holidays.sync', 'filter' => 'auth:admin.holidays.sync']);
    $routes->post('holidays/off-days', 'HolidayController::updateOffDays', ['as' => 'admin.holidays.off-days', 'filter' => 'auth:admin.off-days.update']);
    $routes->get('holidays/(:uuid)/edit', 'HolidayController::edit/$1', ['as' => 'admin.holidays.edit', 'filter' => 'auth:admin.holidays.update']);
    $routes->post('holidays/(:uuid)', 'HolidayController::update/$1', ['as' => 'admin.holidays.update', 'filter' => 'auth:admin.holidays.update']);
    $routes->post('holidays/(:uuid)/delete', 'HolidayController::delete/$1', ['as' => 'admin.holidays.delete', 'filter' => 'auth:admin.holidays.delete']);
});
