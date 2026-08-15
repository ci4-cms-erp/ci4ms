<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Locks permission_string() (app/Common.php), the single source of the
 * "<pagename>.<action>" RBAC identifier used by AuthGroups (definition +
 * group matrix), the permission-grant controllers, Ci4MsAuthFilter's can()
 * check and the sidebar visibility check.
 *
 * The whole RBAC chain relies on this always lowercasing the pagename so a
 * grant and its later check can never diverge on case; these tests are the
 * guard that keeps that contract if the helper is ever touched.
 *
 * @internal
 */
final class PermissionStringTest extends CIUnitTestCase
{
    public function testLowercasesThePagename(): void
    {
        $this->assertSame('users.read', permission_string('Users', 'read'));
        $this->assertSame('users.userscrud.update', permission_string('Users.usersCrud', 'update'));
    }

    public function testLeavesTheActionUntouched(): void
    {
        // The action half is always a lowercase literal at every call site, so
        // the helper does not normalize it -- document that here.
        $this->assertSame('blog.blog.create', permission_string('Blog.blog', 'create'));
    }

    public function testIsIdempotentForAlreadyLowercaseInput(): void
    {
        $once  = permission_string('methods.methodlist', 'read');
        $twice = permission_string(strtolower('methods.methodlist'), 'read');

        $this->assertSame('methods.methodlist.read', $once);
        $this->assertSame($once, $twice);
    }

    public function testDefinitionAndCheckSitesProduceIdenticalStrings(): void
    {
        // AuthGroups defines the permission from strtolower($page->pagename)
        // and Ci4MsAuthFilter checks the same way; both now route through this
        // helper, so a raw pagename and its pre-lowercased form must collapse
        // to the same identifier.
        $defined = permission_string('Pages.pageList', 'delete');
        $checked = permission_string('pages.pagelist', 'delete');

        $this->assertSame($defined, $checked);
        $this->assertSame('pages.pagelist.delete', $defined);
    }
}
