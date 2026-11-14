<?php

use App\Entities\SlaPolicy;
use App\Exceptions\BusinessException;
use App\Models\SlaPolicyModel;
use App\Services\SlaPolicyService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SlaPolicyServiceTest extends CIUnitTestCase
{
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name'               => 'Urgent',
            'priority'           => SlaPolicy::PRIORITY_URGENT,
            'response_minutes'   => '60',
            'resolution_minutes' => '240',
            'description'        => '  ',
            'is_holiday'         => '1',
            'is_off_day'         => '0',
            'is_work_hours'      => '1',
            'is_active'          => '1',
        ], $overrides);
    }

    public function testCreateCastsWorkHoursFlagOffToZero(): void
    {
        $model = $this->createMock(SlaPolicyModel::class);
        $model->expects($this->once())
            ->method('insert')
            ->with($this->callback(static fn (array $row) => $row['is_work_hours'] === 0), true)
            ->willReturn('01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0b');
        $model->method('find')->willReturn(new SlaPolicy());

        (new SlaPolicyService($model))->create($this->validData(['is_work_hours' => '0']));
    }

    public function testCreateRejectsResolutionFasterThanResponse(): void
    {
        $model = $this->createMock(SlaPolicyModel::class);
        $model->expects($this->never())->method('insert');

        $this->expectException(BusinessException::class);

        (new SlaPolicyService($model))->create($this->validData(['resolution_minutes' => '30']));
    }

    public function testCreateNormalizesFlagsAndEmptyDescription(): void
    {
        $model = $this->createMock(SlaPolicyModel::class);
        $model->expects($this->once())
            ->method('insert')
            ->with($this->callback(static fn (array $row) => $row['is_active'] === 1
                && $row['is_holiday'] === 1
                && $row['is_off_day'] === 0
                && $row['is_work_hours'] === 1
                && $row['description'] === null), true)
            ->willReturn('01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0b');
        $model->method('find')->willReturn(new SlaPolicy());

        (new SlaPolicyService($model))->create($this->validData());
    }

    public function testUpdateAllowsEqualResponseAndResolution(): void
    {
        $id    = '01936b2a-1c2d-7e3f-8a4b-5c6d7e8f9a0b';
        $model = $this->createMock(SlaPolicyModel::class);
        $model->expects($this->once())
            ->method('update')
            ->with($id, $this->callback(static fn (array $row) => $row['id'] === $id && $row['is_active'] === 0))
            ->willReturn(true);

        $policy = new SlaPolicy(['id' => $id]);

        (new SlaPolicyService($model))->update($policy, $this->validData(['resolution_minutes' => '60', 'is_active' => '0']));
    }

    public function testFormatDuration(): void
    {
        helper('format');

        $this->assertSame('45 menit', format_duration(45));
        $this->assertSame('8 jam', format_duration(480));
        $this->assertSame('1 jam 30 menit', format_duration(90));
    }
}
