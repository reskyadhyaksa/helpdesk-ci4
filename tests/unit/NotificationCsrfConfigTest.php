<?php

use CodeIgniter\Test\CIUnitTestCase;
use Config\Filters;

/**
 * Endpoint JSON lonceng tidak ikut verifikasi CSRF, supaya POST "tandai dibaca" dari JS tidak mengganti token
 * (Security::$regenerate) dan membuat form lain yang sedang terbuka ditolak.
 *
 * @internal
 */
final class NotificationCsrfConfigTest extends CIUnitTestCase
{
    public function testNotificationEndpointsAreExemptFromTheCsrfFilter(): void
    {
        $globals = (new Filters())->globals['before'];

        $this->assertArrayHasKey('csrf', $globals);
        $this->assertContains('notifications/*', (array) $globals['csrf']['except']);
    }

    public function testEverythingElseStillGoesThroughTheCsrfFilter(): void
    {
        $except = (array) (new Filters())->globals['before']['csrf']['except'];

        $this->assertSame(['notifications/*'], $except, 'hanya endpoint lonceng yang boleh dikecualikan');
    }
}
