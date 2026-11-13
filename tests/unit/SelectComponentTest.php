<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SelectComponentTest extends CIUnitTestCase
{
    private const OPTIONS = ['urgent' => 'P1 Urgent', 'high' => 'P2 High'];

    public function testFormFieldWithPlaceholderAndError(): void
    {
        $html = component('select', [
            'name'        => 'priority',
            'label'       => 'Prioritas',
            'options'     => self::OPTIONS,
            'selected'    => 'high',
            'placeholder' => 'Pilih prioritas',
            'required'    => true,
            'fullwidth'   => true,
            'error'       => 'Prioritas wajib diisi.',
        ]);

        $this->assertStringContainsString('<label class="label" for="priority">', $html);
        $this->assertStringContainsString('<option value="">Pilih prioritas</option>', $html);
        $this->assertStringContainsString('<option value="high" selected>P2 High</option>', $html);
        $this->assertStringContainsString('<option value="urgent" >P1 Urgent</option>', $html);
        $this->assertStringContainsString('class="select is-fullwidth is-danger"', $html);
        $this->assertStringContainsString('aria-describedby="priorityError"', $html);
        $this->assertStringContainsString('id="priorityError">Prioritas wajib diisi.</p>', $html);
    }

    public function testInlineWithHiddenLabel(): void
    {
        $html = component('select', [
            'name'      => 'role_id',
            'id'        => 'role-1',
            'label'     => 'Role untuk Budi',
            'hideLabel' => true,
            'options'   => self::OPTIONS,
            'selected'  => 'urgent',
            'size'      => 'is-small',
        ]);

        $this->assertStringContainsString('<label class="is-sr-only" for="role-1">', $html);
        $this->assertStringContainsString('class="select is-small"', $html);
        $this->assertStringContainsString('<option value="urgent" selected>', $html);
        $this->assertStringNotContainsString('aria-invalid', $html);
        $this->assertStringNotContainsString('has-text-danger', $html);
    }
}
