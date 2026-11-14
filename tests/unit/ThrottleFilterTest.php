<?php

use App\Filters\ThrottleFilter;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Throttle\Throttler;
use Config\Services;

/**
 * @internal
 */
final class ThrottleFilterTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        Services::resetSingle('throttler');
        parent::tearDown();
    }

    private function request(): IncomingRequest
    {
        $request = $this->createMock(IncomingRequest::class);
        $request->method('getIPAddress')->willReturn('10.0.0.5');

        return $request;
    }

    public function testAllowsRequestWhileUnderTheLimit(): void
    {
        $throttler = $this->createMock(Throttler::class);
        $throttler->method('check')->willReturn(true);
        Services::injectMock('throttler', $throttler);

        $this->assertNull((new ThrottleFilter())->before($this->request()));
    }

    public function testBlocksWith429WhenOverTheLimit(): void
    {
        $throttler = $this->createMock(Throttler::class);
        $throttler->method('check')->willReturn(false);
        Services::injectMock('throttler', $throttler);

        $response = (new ThrottleFilter())->before($this->request());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(429, $response->getStatusCode());
    }

    public function testKeyIncludesClientIpAndRoutePath(): void
    {
        $throttler = $this->createMock(Throttler::class);
        $throttler->expects($this->once())
            ->method('check')
            ->with($this->callback(static fn (string $key) => str_contains($key, md5('10.0.0.5'))), 5, 600)
            ->willReturn(true);
        Services::injectMock('throttler', $throttler);

        (new ThrottleFilter())->before($this->request(), ['5', '600']);
    }
}
