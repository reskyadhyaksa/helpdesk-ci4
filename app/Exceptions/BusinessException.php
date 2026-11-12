<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis. Pesannya aman ditampilkan ke user.
 */
class BusinessException extends RuntimeException
{
}
