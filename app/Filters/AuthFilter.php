<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 'auth'                           → wajib login.
 * 'auth:admin.access,foo.bar'      → wajib login dan memiliki SEMUA permission yang disebut.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');

        if (! $auth->check()) {
            return redirect()->to(url_to('login'))->with('danger', lang('Auth.loginRequired'));
        }

        foreach ($arguments ?? [] as $permission) {
            if (! $auth->can($permission)) {
                return service('response')
                    ->setStatusCode(403)
                    ->setBody(view('errors/html/error_403'));
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
