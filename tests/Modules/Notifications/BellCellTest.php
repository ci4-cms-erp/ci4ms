<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Notifications\Cells\BellCell;

/**
 * BellCell crash-safety unit tests.
 *
 * base.php renders this cell on every backend page, so it must never fault when
 * there is no session. The non-positive-user guard returns before any database
 * work, so it is verified here DB-free. The table-missing guard shares the same
 * shape as Notifier::unreadCount's guard, which is covered in NotifierTest.
 *
 * @internal
 */
final class BellCellTest extends CIUnitTestCase
{
    /**
     * render() returns an empty string for a non-positive user id.
     *
     * Reproduces the login-page / no-session case: the cell must emit nothing
     * and must not reach the database.
     *
     * @return void
     */
    public function testRenderReturnsEmptyForNonPositiveUser(): void
    {
        $cell = new BellCell();

        $this->assertSame('', $cell->render(['user_id' => 0]));
        $this->assertSame('', $cell->render(['user_id' => -3]));
    }
}
