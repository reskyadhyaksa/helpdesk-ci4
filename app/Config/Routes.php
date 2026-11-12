<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

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
});
