<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Modules\Notifications\Controllers\NotificationController;
use ReflectionClass;
use Tests\Support\Notifications\FakeCommonModel;

/**
 * NotificationController crash-safety unit tests (DB-free).
 *
 * NotificationsConfig::$menus always renders a "Bildirimler" link in the left
 * menu, so index()/feed() are reachable even when the notifications table was
 * never migrated. These tests pin the table-missing guards: the controller is
 * built with newInstanceWithoutConstructor and its collaborators are injected
 * (a FakeCommonModel whose fake connection reports the table absent, a stub
 * request, a real Response), so the guard branch runs without a live database.
 *
 * index() always renders the full backend view (sidebar/defData) and so is not
 * unit-testable in isolation; its guard shares the exact same shape and is
 * verified structurally. feed() short-circuits with respond() before any view,
 * so its guard is exercised end to end here.
 *
 * @internal
 */
final class NotificationControllerTest extends CIUnitTestCase
{
    /**
     * Builds a NotificationController with injected collaborators, no constructor.
     *
     * @param FakeCommonModel $model  Injected over the public $commonModel.
     * @param bool            $isAjax What the stub request reports for isAJAX().
     *
     * @return NotificationController The wired-up controller.
     */
    private function makeController(FakeCommonModel $model, bool $isAjax): NotificationController
    {
        /** @var NotificationController $controller */
        $controller              = (new ReflectionClass(NotificationController::class))->newInstanceWithoutConstructor();
        $controller->commonModel = $model;

        $request = new class ($isAjax) {
            public function __construct(private bool $ajax) {}

            public function isAJAX(): bool
            {
                return $this->ajax;
            }
        };

        $this->setPrivateProperty($controller, 'request', $request);
        $this->setPrivateProperty($controller, 'response', Services::response(null, false));

        return $controller;
    }

    /**
     * feed() returns an empty payload and never queries when the table is absent.
     *
     * Reproduces the "module dropped, migration not run" case reached by hitting
     * the feed URL directly: the guard must respond instead of letting the
     * unguarded lists() fatal.
     *
     * @return void
     */
    public function testFeedReturnsEmptyPayloadWhenTableMissing(): void
    {
        $model = new FakeCommonModel();
        $model->setTableExists(false);
        $model->listsReturn = [(object) ['id' => 1]]; // would leak in if the guard were skipped

        $controller = $this->makeController($model, true);

        $response = $controller->feed();
        $payload  = json_decode((string) $response->getBody(), true);

        $this->assertSame(['status' => true, 'unread' => 0, 'items' => []], $payload);
        $this->assertNull($model->lastListsTable, 'the guard must short-circuit before lists()');
    }

    /**
     * feed() stays AJAX-only: a non-AJAX request is forbidden before the guard.
     *
     * @return void
     */
    public function testFeedRejectsNonAjax(): void
    {
        $controller = $this->makeController(new FakeCommonModel(), false);

        $this->assertSame(403, $controller->feed()->getStatusCode());
    }
}
