<?php

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Backend\Libraries\BackendMaintenance;

/**
 * @internal
 */
final class BackendMaintenanceTest extends CIUnitTestCase
{
    public function testModuleFromControllerReturnsSecondSegment(): void
    {
        $this->assertSame('Blog', BackendMaintenance::moduleFromController('Modules\\Blog\\Controllers\\Tags'));
        $this->assertSame('Pages', BackendMaintenance::moduleFromController('\\Modules\\Pages\\Controllers\\Pages'));
    }

    public function testModuleFromControllerReturnsNullForNonModule(): void
    {
        $this->assertNull(BackendMaintenance::moduleFromController('App\\Controllers\\Home'));
        $this->assertNull(BackendMaintenance::moduleFromController('Modules'));
    }

    public function testModuleFromDbClassNameHandlesLeadingDash(): void
    {
        $this->assertSame('Blog', BackendMaintenance::moduleFromDbClassName('-Modules-Blog-Controllers-Blog'));
        $this->assertSame('Pages', BackendMaintenance::moduleFromDbClassName('Modules-Pages-Controllers-Pages'));
    }

    public function testSuperadminIsNeverBlocked(): void
    {
        $maintenance = ['all' => true, 'modules' => ['Blog']];
        $this->assertFalse(BackendMaintenance::isBlocked($maintenance, 'Modules\\Blog\\Controllers\\Blog', true));
    }

    public function testAllBlocksEveryNonSuperadmin(): void
    {
        $maintenance = ['all' => true, 'modules' => []];
        $this->assertTrue(BackendMaintenance::isBlocked($maintenance, 'Modules\\Pages\\Controllers\\Pages', false));
    }

    public function testModuleInListIsBlocked(): void
    {
        $maintenance = ['all' => false, 'modules' => ['Blog']];
        $this->assertTrue(BackendMaintenance::isBlocked($maintenance, 'Modules\\Blog\\Controllers\\Tags', false));
    }

    public function testModuleNotInListIsNotBlocked(): void
    {
        $maintenance = ['all' => false, 'modules' => ['Blog']];
        $this->assertFalse(BackendMaintenance::isBlocked($maintenance, 'Modules\\Pages\\Controllers\\Pages', false));
    }

    public function testNonModuleControllerIsNotBlockedWhenNotAll(): void
    {
        $maintenance = ['all' => false, 'modules' => ['Blog']];
        $this->assertFalse(BackendMaintenance::isBlocked($maintenance, 'App\\Controllers\\Home', false));
    }

    public function testModuleInMapIsBlocked(): void
    {
        $maintenance = ['all' => false, 'modules' => ['Blog' => time() + 600]];
        $this->assertTrue(BackendMaintenance::isBlocked($maintenance, 'Modules\\Blog\\Controllers\\Tags', false));
    }

    public function testModuleInMapWithNullUntilIsBlocked(): void
    {
        $maintenance = ['all' => false, 'modules' => ['Blog' => null]];
        $this->assertTrue(BackendMaintenance::isBlocked($maintenance, 'Modules\\Blog\\Controllers\\Blog', false));
    }

    public function testModuleNotInMapIsNotBlocked(): void
    {
        $maintenance = ['all' => false, 'modules' => ['Blog' => time() + 600]];
        $this->assertFalse(BackendMaintenance::isBlocked($maintenance, 'Modules\\Pages\\Controllers\\Pages', false));
    }

    public function testNormalizeReturnsDefaultsForNullAndNonArray(): void
    {
        $expected = ['all' => false, 'until' => null, 'modules' => []];
        $this->assertSame($expected, BackendMaintenance::normalize(null));
        $this->assertSame($expected, BackendMaintenance::normalize('garbage'));
    }

    public function testNormalizeConvertsLegacyListToMap(): void
    {
        $result = BackendMaintenance::normalize(['all' => false, 'modules' => ['Blog', 'Pages']]);
        $this->assertSame(['Blog' => null, 'Pages' => null], $result['modules']);
        $this->assertFalse($result['all']);
        $this->assertNull($result['until']);
    }

    public function testNormalizeAcceptsStdClassAndNestedModulesMap(): void
    {
        $until  = time() + 600;
        $result = BackendMaintenance::normalize(json_decode(json_encode([
            'all'     => true,
            'until'   => (string) $until,
            'modules' => ['Blog' => $until, 'Pages' => null],
        ])));

        $this->assertTrue($result['all']);
        $this->assertSame($until, $result['until']);
        $this->assertSame(['Blog' => $until, 'Pages' => null], $result['modules']);
    }

    public function testNormalizeTreatsEmptyZeroAndNegativeUntilAsNull(): void
    {
        $result = BackendMaintenance::normalize([
            'until'   => 0,
            'modules' => ['Blog' => '', 'Pages' => -5],
        ]);

        $this->assertNull($result['until']);
        $this->assertSame(['Blog' => null, 'Pages' => null], $result['modules']);
    }

    public function testUntilForReturnsModuleUntilWhenModuleIsInMaintenance(): void
    {
        $moduleUntil = time() + 300;
        $maintenance = ['all' => true, 'until' => time() + 900, 'modules' => ['Blog' => $moduleUntil]];
        $this->assertSame($moduleUntil, BackendMaintenance::untilFor($maintenance, 'Modules\\Blog\\Controllers\\Blog'));
    }

    public function testUntilForReturnsNullForModuleWithoutDuration(): void
    {
        $maintenance = ['all' => false, 'until' => null, 'modules' => ['Blog' => null]];
        $this->assertNull(BackendMaintenance::untilFor($maintenance, 'Modules\\Blog\\Controllers\\Blog'));
    }

    public function testUntilForFallsBackToGlobalUntilWhenAllIsOn(): void
    {
        $globalUntil = time() + 900;
        $maintenance = ['all' => true, 'until' => $globalUntil, 'modules' => []];
        $this->assertSame($globalUntil, BackendMaintenance::untilFor($maintenance, 'Modules\\Pages\\Controllers\\Pages'));
    }

    public function testUntilForReturnsNullWhenNothingBlocks(): void
    {
        $maintenance = ['all' => false, 'until' => time() + 900, 'modules' => []];
        $this->assertNull(BackendMaintenance::untilFor($maintenance, 'Modules\\Pages\\Controllers\\Pages'));
    }

    public function testSecondsUntilEndReturnsNullWithoutUntil(): void
    {
        $this->assertNull(BackendMaintenance::secondsUntilEnd([]));
        $this->assertNull(BackendMaintenance::secondsUntilEnd(['until' => null]));
        $this->assertNull(BackendMaintenance::secondsUntilEnd(['until' => '']));
        $this->assertNull(BackendMaintenance::secondsUntilEnd(['until' => 0]));
        $this->assertNull(BackendMaintenance::secondsUntilEnd(['until' => -10]));
    }

    public function testSecondsUntilEndReturnsZeroForPastUntil(): void
    {
        $this->assertSame(0, BackendMaintenance::secondsUntilEnd(['until' => time() - 60]));
    }

    public function testSecondsUntilEndReturnsRemainingSecondsForFutureUntil(): void
    {
        $seconds = BackendMaintenance::secondsUntilEnd(['until' => time() + 120]);
        $this->assertNotNull($seconds);
        $this->assertGreaterThan(115, $seconds);
        $this->assertLessThanOrEqual(120, $seconds);
    }

    public function testModuleInMaintenanceReturnsTrueWhenModuleIsInMap(): void
    {
        $maintenance = ['all' => false, 'until' => null, 'modules' => ['Blog' => null, 'Pages' => time() + 600]];
        $this->assertTrue(BackendMaintenance::moduleInMaintenance($maintenance, '-Modules-Blog-Controllers-Blog'));
        $this->assertTrue(BackendMaintenance::moduleInMaintenance($maintenance, '-Modules-Pages-Controllers-Pages'));
    }

    public function testModuleInMaintenanceReturnsFalseWhenModuleNotInMap(): void
    {
        $maintenance = ['all' => true, 'until' => time() + 600, 'modules' => ['Blog' => null]];
        $this->assertFalse(BackendMaintenance::moduleInMaintenance($maintenance, '-Modules-Pages-Controllers-Pages'));
        $this->assertFalse(BackendMaintenance::moduleInMaintenance(['all' => false, 'until' => null, 'modules' => []], '-Modules-Blog-Controllers-Blog'));
    }

    public function testModuleInMaintenanceReturnsFalseForNonModuleClassName(): void
    {
        $maintenance = ['all' => false, 'until' => null, 'modules' => ['Blog' => null]];
        $this->assertFalse(BackendMaintenance::moduleInMaintenance($maintenance, '-App-Controllers-Home'));
        $this->assertFalse(BackendMaintenance::moduleInMaintenance($maintenance, 'Modules'));
    }

    public function testSelectableModulesAreDistinctSortedAndExcludeInfra(): void
    {
        $classNames = [
            '-Modules-Blog-Controllers-Blog',
            '-Modules-Blog-Controllers-Tags',
            '-Modules-Pages-Controllers-Pages',
            '-Modules-Auth-Controllers-Login',
            '-Modules-Backend-Controllers-Backend',
            '-Modules-Install-Controllers-Install',
        ];
        $this->assertSame(['Blog', 'Pages'], BackendMaintenance::selectableModules($classNames));
    }
}
