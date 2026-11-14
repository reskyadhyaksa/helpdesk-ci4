<?php

use App\Models\PermissionModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PermissionModelTest extends CIUnitTestCase
{
    public function testDefinesCategoryAndTicketPermissions(): void
    {
        $slugs = PermissionModel::definedSlugs();

        foreach ([
            'admin.request-categories.view',
            'admin.request-categories.create',
            'admin.request-categories.update',
            'admin.request-categories.delete',
            'admin.users.category-access',
            'admin.tickets.view-all',
        ] as $slug) {
            $this->assertContains($slug, $slugs);
        }
    }

    public function testPermissionSlugsAreUnique(): void
    {
        $slugs = PermissionModel::definedSlugs();

        $this->assertSame(count($slugs), count(array_unique($slugs)));
    }
}
