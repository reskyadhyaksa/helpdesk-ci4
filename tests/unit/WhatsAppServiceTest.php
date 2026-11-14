<?php

use App\Services\WhatsAppService;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Integration;

/**
 * @internal
 */
final class WhatsAppServiceTest extends CIUnitTestCase
{
    private function config(string $token = 'secret'): Integration
    {
        $config                = new Integration();
        $config->whatsappApiUrl   = 'https://wa.test/send-message';
        $config->whatsappApiToken = $token;

        return $config;
    }

    private function response(int $status): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);

        return $response;
    }

    public function testSendMessagePostsNumberAndMessageWithBearerToken(): void
    {
        $client = $this->createMock(CURLRequest::class);
        $client->expects($this->once())
            ->method('post')
            ->with('https://wa.test/send-message', $this->callback(static fn (array $options) => $options['json'] === ['number' => '628123456789', 'message' => 'Halo']
                && $options['headers']['Authorization'] === 'Bearer secret'))
            ->willReturn($this->response(200));

        $this->assertTrue((new WhatsAppService($client, $this->config()))->sendMessage('628123456789', 'Halo'));
    }

    public function testSendMessageOmitsAuthorizationWhenNoToken(): void
    {
        $client = $this->createMock(CURLRequest::class);
        $client->expects($this->once())
            ->method('post')
            ->with($this->anything(), $this->callback(static fn (array $options) => ! isset($options['headers']['Authorization'])))
            ->willReturn($this->response(200));

        (new WhatsAppService($client, $this->config('')))->sendMessage('628123456789', 'Halo');
    }

    public function testSendMessageReturnsFalseOnServerError(): void
    {
        $client = $this->createMock(CURLRequest::class);
        $client->method('post')->willReturn($this->response(500));

        $this->assertFalse((new WhatsAppService($client, $this->config()))->sendMessage('628123456789', 'Halo'));
    }

    public function testSendMessageReturnsFalseWhenConnectionThrows(): void
    {
        $client = $this->createMock(CURLRequest::class);
        $client->method('post')->willThrowException(new RuntimeException('timeout'));

        $this->assertFalse((new WhatsAppService($client, $this->config()))->sendMessage('628123456789', 'Halo'));
    }

    public function testSendMessageReturnsFalseWhenNotConfigured(): void
    {
        $client = $this->createMock(CURLRequest::class);
        $client->expects($this->never())->method('post');

        $config                = new Integration();
        $config->whatsappApiUrl = '';

        $this->assertFalse((new WhatsAppService($client, $config))->sendMessage('628123456789', 'Halo'));
    }

    public function testSendOtpReturnsSixDigitCodeAndSendsItInTheMessage(): void
    {
        $sent   = null;
        $client = $this->createMock(CURLRequest::class);
        $client->method('post')->willReturnCallback(function ($url, array $options) use (&$sent) {
            $sent = $options['json']['message'];

            return $this->response(200);
        });

        $code = (new WhatsAppService($client, $this->config()))->sendOtp('628123456789');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertStringContainsString($code, $sent);
    }

    public function testSendOtpReturnsNullWhenSendingFails(): void
    {
        $client = $this->createMock(CURLRequest::class);
        $client->method('post')->willReturn($this->response(500));

        $this->assertNull((new WhatsAppService($client, $this->config()))->sendOtp('628123456789'));
    }
}
