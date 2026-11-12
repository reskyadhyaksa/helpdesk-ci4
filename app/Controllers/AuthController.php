<?php

namespace App\Controllers;

use App\Exceptions\BusinessException;
use CodeIgniter\HTTP\RedirectResponse;

class AuthController extends BaseController
{
    public function showLogin(): string
    {
        return view('auth/login', ['title' => 'Login']);
    }

    public function login(): RedirectResponse
    {
        if (! $this->validate('login')) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $input = $this->validator->getValidated();

        try {
            service('auth')->login($input['username'], $input['password']);
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to(service('auth')->homeRoute()));
    }

    public function logout(): RedirectResponse
    {
        service('auth')->logout();

        return redirect()->to(url_to('home'))->with('success', lang('Auth.loggedOut'));
    }
}
