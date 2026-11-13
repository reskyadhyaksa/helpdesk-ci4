<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Primary key semua tabel adalah UUIDv7
$routes->addPlaceholder('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}');

// Publik
$routes->get('/', 'HomeController::index', ['as' => 'home']);

// Tamu saja
$routes->group('', ['filter' => 'guest'], static function (RouteCollection $routes) {
    $routes->get('login', 'AuthController::showLogin', ['as' => 'login']);
    $routes->post('login', 'AuthController::login', ['as' => 'login.attempt']);
});

$routes->post('logout', 'AuthController::logout', ['as' => 'logout', 'filter' => 'auth']);

// Area user: /u
$routes->group('u', ['namespace' => 'App\Controllers\User', 'filter' => 'auth:user.dashboard.view'], static function (RouteCollection $routes) {
    $routes->get('/', 'DashboardController::index', ['as' => 'user.dashboard']);
});

// Area admin: /admin. Gerbang area = admin.access, tiap halaman menambah permission-nya sendiri.
$routes->group('admin', ['namespace' => 'App\Controllers\Admin', 'filter' => 'auth:admin.access'], static function (RouteCollection $routes) {
    $routes->get('/', 'DashboardController::index', ['as' => 'admin.dashboard', 'filter' => 'auth:admin.dashboard.view']);

    // Halaman kerangka: cukup gerbang admin.access. Tambah permission per halaman saat fiturnya dibuat.
    $routes->get('tickets', 'TicketController::index', ['as' => 'admin.tickets.index']);
    $routes->get('tickets/mine', 'TicketController::mine', ['as' => 'admin.tickets.mine']);
    $routes->get('request-categories', 'RequestCategoryController::index', ['as' => 'admin.request-categories.index']);
    $routes->get('faqs', 'FaqController::index', ['as' => 'admin.faqs.index']);
    $routes->get('forms', 'FormController::index', ['as' => 'admin.forms.index']);
    $routes->get('reports', 'ReportController::index', ['as' => 'admin.reports.index']);
    $routes->get('holidays', 'HolidayController::index', ['as' => 'admin.holidays.index']);

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

    // SLA Policies
    $routes->get('sla-policies', 'SlaPolicyController::index', ['as' => 'admin.sla-policies.index', 'filter' => 'auth:admin.sla-policies.view']);
    $routes->get('sla-policies/new', 'SlaPolicyController::new', ['as' => 'admin.sla-policies.new', 'filter' => 'auth:admin.sla-policies.create']);
    $routes->post('sla-policies', 'SlaPolicyController::create', ['as' => 'admin.sla-policies.create', 'filter' => 'auth:admin.sla-policies.create']);
    $routes->get('sla-policies/(:uuid)/edit', 'SlaPolicyController::edit/$1', ['as' => 'admin.sla-policies.edit', 'filter' => 'auth:admin.sla-policies.update']);
    $routes->post('sla-policies/(:uuid)', 'SlaPolicyController::update/$1', ['as' => 'admin.sla-policies.update', 'filter' => 'auth:admin.sla-policies.update']);
    $routes->post('sla-policies/(:uuid)/delete', 'SlaPolicyController::delete/$1', ['as' => 'admin.sla-policies.delete', 'filter' => 'auth:admin.sla-policies.delete']);
});
