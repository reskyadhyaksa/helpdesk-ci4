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

if (! function_exists('auth_can')) {
    /**
     * Apakah user yang login memiliki permission ini. Dipakai view untuk menampilkan tombol aksi.
     */
    function auth_can(string $permission): bool
    {
        return service('auth')->can($permission);
    }
}
