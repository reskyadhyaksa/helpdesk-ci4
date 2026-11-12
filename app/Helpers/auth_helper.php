<?php

if (! function_exists('auth_user')) {
    /**
     * Data user yang sedang login (dari session), atau null jika belum login.
     */
    function auth_user(): ?array
    {
        return service('auth')->user();
    }
}
