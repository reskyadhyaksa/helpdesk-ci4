<?php

use App\Exceptions\BusinessException;
use App\Models\SystemParameterModel;
use App\Services\SystemParameterService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SystemParameterServiceTest extends CIUnitTestCase
{
    private SystemParameterModel $parameters;

    /** @var array<string, string> nilai tersimpan sekarang */
    private array $stored = [];

    /** @var array<string, string> hasil put() */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->parameters = $this->createMock(SystemParameterModel::class);
        $this->parameters->method('value')->willReturnCallback(fn (string $key): string => $this->stored[$key] ?? SystemParameterModel::DEFAULTS[$key]['value']);
        $this->parameters->method('put')->willReturnCallback(function (string $key, string $value): void {
            $this->written[$key] = $value;
        });
    }

    private function service(string $uploadMax = '2M', string $postMax = '8M'): SystemParameterService
    {
        return new SystemParameterService($this->parameters, static fn (string $name): string => $name === 'upload_max_filesize' ? $uploadMax : $postMax);
    }

    private function valid(array $overrides = []): array
    {
        return $overrides + [
            SystemParameterModel::ATTACHMENT_MAX_SIZE_MB        => '2',
            SystemParameterModel::ATTACHMENT_MAX_FILES          => '3',
            SystemParameterModel::ATTACHMENT_ALLOWED_EXTENSIONS => 'jpg,png,pdf',
            SystemParameterModel::ATTACHMENT_RETENTION_DAYS     => '14',
            SystemParameterModel::NOTIFY_EMAIL_ENABLED          => '0',
            SystemParameterModel::NOTIFY_WHATSAPP_ENABLED       => '1',
            SystemParameterModel::SLA_WARNING_MINUTES           => '30',
            SystemParameterModel::SLA_ALERT_MAX_AGE_HOURS       => '24',
        ];
    }

    private function assertRejected(array $input, string $service = 'default'): void
    {
        try {
            $this->service()->update($input);
            $this->fail('BusinessException diharapkan');
        } catch (BusinessException) {
            $this->assertSame([], $this->written, 'tidak boleh ada yang tersimpan bila ada kesalahan');
        }
    }

    public function testGroupsListEveryParameterWithItsCurrentValue(): void
    {
        $this->stored[SystemParameterModel::ATTACHMENT_RETENTION_DAYS] = '30';

        $groups = $this->service()->groups();

        $this->assertEqualsCanonicalizing(['attachment', 'notification', 'sla'], array_keys($groups));
        $byKey = [];

        foreach ($groups as $items) {
            foreach ($items as $item) {
                $byKey[$item['key']] = $item;
            }
        }

        $this->assertCount(count(SystemParameterModel::DEFAULTS), $byKey);
        $this->assertSame('30', $byKey[SystemParameterModel::ATTACHMENT_RETENTION_DAYS]['value']);
        $this->assertNotSame('', $byKey[SystemParameterModel::SLA_WARNING_MINUTES]['label']);
    }

    public function testAValidFormIsSavedValueByValue(): void
    {
        $this->service()->update($this->valid([SystemParameterModel::ATTACHMENT_RETENTION_DAYS => '30']));

        $this->assertSame('30', $this->written[SystemParameterModel::ATTACHMENT_RETENTION_DAYS]);
        $this->assertSame('1', $this->written[SystemParameterModel::NOTIFY_WHATSAPP_ENABLED]);
        $this->assertSame('jpg,png,pdf', $this->written[SystemParameterModel::ATTACHMENT_ALLOWED_EXTENSIONS]);
    }

    public function testUnknownKeysAreIgnored(): void
    {
        $this->service()->update($this->valid(['HACK_KEY' => 'x']));

        $this->assertArrayNotHasKey('HACK_KEY', $this->written);
    }

    public function testRetentionOfZeroIsAllowedToDisableThePurge(): void
    {
        $this->service()->update($this->valid([SystemParameterModel::ATTACHMENT_RETENTION_DAYS => '0']));

        $this->assertSame('0', $this->written[SystemParameterModel::ATTACHMENT_RETENTION_DAYS]);
    }

    public function testNumbersMustBeWholeAndInRange(): void
    {
        foreach ([
            [SystemParameterModel::ATTACHMENT_MAX_SIZE_MB, '0'],
            [SystemParameterModel::ATTACHMENT_MAX_SIZE_MB, 'abc'],
            [SystemParameterModel::ATTACHMENT_MAX_SIZE_MB, '-1'],
            [SystemParameterModel::ATTACHMENT_MAX_FILES, '0'],
            [SystemParameterModel::ATTACHMENT_MAX_FILES, '999'],
            [SystemParameterModel::ATTACHMENT_RETENTION_DAYS, '-3'],
            [SystemParameterModel::ATTACHMENT_RETENTION_DAYS, '99999'],
            [SystemParameterModel::SLA_WARNING_MINUTES, '0'],
            [SystemParameterModel::SLA_ALERT_MAX_AGE_HOURS, '1.5'],
        ] as [$key, $value]) {
            $this->assertRejected($this->valid([$key => $value]));
        }
    }

    public function testBooleansMustBeZeroOrOne(): void
    {
        $this->assertRejected($this->valid([SystemParameterModel::NOTIFY_EMAIL_ENABLED => '2']));
        $this->assertRejected($this->valid([SystemParameterModel::NOTIFY_EMAIL_ENABLED => 'ya']));
    }

    public function testExtensionListMustBeLowercaseAlphanumericAndCommaSeparated(): void
    {
        foreach (['', 'jpg,,png', 'jpg png', '.jpg', 'jp g', 'jpg;png', str_repeat('a', 11)] as $bad) {
            $this->assertRejected($this->valid([SystemParameterModel::ATTACHMENT_ALLOWED_EXTENSIONS => $bad]));
        }
    }

    public function testExecutableAndScriptExtensionsCanNeverBeAllowed(): void
    {
        foreach (['jpg,php', 'png,phtml', 'pdf,exe', 'jpg,html', 'svg', 'bat,jpg', 'js'] as $bad) {
            $this->assertRejected($this->valid([SystemParameterModel::ATTACHMENT_ALLOWED_EXTENSIONS => $bad]));
        }
    }

    public function testExtensionListIsNormalizedToLowercaseAndTrimmed(): void
    {
        $this->service()->update($this->valid([SystemParameterModel::ATTACHMENT_ALLOWED_EXTENSIONS => ' JPG,Png ']));

        $this->assertSame('jpg,png', $this->written[SystemParameterModel::ATTACHMENT_ALLOWED_EXTENSIONS]);
    }

    public function testFileSizeCannotExceedPhpsUploadMaxFilesize(): void
    {
        $this->assertRejected($this->valid([SystemParameterModel::ATTACHMENT_MAX_SIZE_MB => '3']));
    }

    public function testFileSizeTimesCountCannotExceedPhpsPostMaxSize(): void
    {
        $service = $this->service('4M', '8M');

        try {
            $service->update($this->valid([SystemParameterModel::ATTACHMENT_MAX_SIZE_MB => '4', SystemParameterModel::ATTACHMENT_MAX_FILES => '3']));
            $this->fail('BusinessException diharapkan');
        } catch (BusinessException) {
            $this->assertSame([], $this->written);
        }

        $service->update($this->valid([SystemParameterModel::ATTACHMENT_MAX_SIZE_MB => '4', SystemParameterModel::ATTACHMENT_MAX_FILES => '2']));
        $this->assertSame('2', $this->written[SystemParameterModel::ATTACHMENT_MAX_FILES]);
    }

    public function testPostMaxSizeOfZeroMeansUnlimited(): void
    {
        $this->service('50M', '0')->update($this->valid([SystemParameterModel::ATTACHMENT_MAX_SIZE_MB => '50', SystemParameterModel::ATTACHMENT_MAX_FILES => '20']));

        $this->assertSame('50', $this->written[SystemParameterModel::ATTACHMENT_MAX_SIZE_MB]);
    }

    public function testAllErrorsAreReportedTogetherAndNothingIsSaved(): void
    {
        try {
            $this->service()->update($this->valid([
                SystemParameterModel::ATTACHMENT_RETENTION_DAYS => '-1',
                SystemParameterModel::SLA_WARNING_MINUTES       => 'x',
            ]));
            $this->fail('BusinessException diharapkan');
        } catch (BusinessException $e) {
            $this->assertSame([], $this->written);
            $this->assertStringContainsString(SystemParameterModel::DEFAULTS[SystemParameterModel::ATTACHMENT_RETENTION_DAYS]['label'], $e->getMessage());
            $this->assertStringContainsString(SystemParameterModel::DEFAULTS[SystemParameterModel::SLA_WARNING_MINUTES]['label'], $e->getMessage());
        }
    }

    public function testPhpLimitsAreParsedFromShorthandNotation(): void
    {
        $limits = (new SystemParameterService($this->parameters, static fn (string $n): string => $n === 'upload_max_filesize' ? '512K' : '1G'))->phpLimits();

        $this->assertSame(0, $limits['upload_mb']);
        $this->assertSame(1024, $limits['post_mb']);
    }
}
