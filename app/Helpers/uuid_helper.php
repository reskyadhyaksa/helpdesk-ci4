<?php

if (! function_exists('uuid_v7')) {
    function uuid_v7(): string
    {
        $ms    = (int) floor(microtime(true) * 1000);
        $bytes = substr(pack('J', $ms), 2) . random_bytes(10);

        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x70); // versi 7
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80); // variant RFC 9562

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
