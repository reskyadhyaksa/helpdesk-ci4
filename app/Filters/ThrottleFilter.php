<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Membatasi laju request per IP dan per route (formulir publik, agar tidak dibanjiri spam).
 * Pemakaian di route: 'filter' => 'throttle:5,600' = maksimal 5 request per 600 detik.
 */
class ThrottleFilter implements FilterInterface
{
    private const DEFAULT_CAPACITY = 5;
    private const DEFAULT_SECONDS  = 600;

    public function before(RequestInterface $request, $arguments = null)
    {
        $capacity = (int) ($arguments[0] ?? self::DEFAULT_CAPACITY);
        $seconds  = (int) ($arguments[1] ?? self::DEFAULT_SECONDS);
        $key      = 'throttle-' . md5($request->getIPAddress()) . '-' . md5($request->getPath());

        if (service('throttler')->check($key, $capacity, $seconds)) {
            return null;
        }

        return service('response')->setStatusCode(429)->setBody(lang('Ticket.tooManyRequests'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
