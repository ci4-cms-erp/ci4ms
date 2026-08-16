<?php

declare(strict_types=1);

namespace Tests\Modules\Pages;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use ci4commonmodel\CommonModel;

/**
 * Equivalence regression test for the Pages::index() DataTables query,
 * converted from a raw db_connect() query-builder chain to
 * CommonModel::lists() (see modules/Pages/Controllers/Pages.php:30-42).
 *
 * Each test runs the pre-conversion raw query and the post-conversion
 * CommonModel call side by side, against the same live rows, and asserts
 * they return identical totals and rows. Read-only: no fixture data is
 * written or mutated by this test.
 */
final class PagesIndexQueryEquivalenceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = false;
    protected $namespace = null;

    /**
     * Reproduces the pre-conversion raw query exactly as it existed before
     * the CommonModel migration.
     *
     * @param string $locale
     * @param string $search
     * @param int    $length
     * @param int    $start
     *
     * @return array{total: int, results: list<object>}
     */
    private function oldRawQuery(string $locale, string $search, int $length, int $start): array
    {
        $db = db_connect();
        $builder = $db->table('pages m');
        $builder->select('m.*, l.title, l.seflink');
        $builder->join('pages_langs l', "l.pages_id = m.id AND l.lang = '{$locale}'", 'left');

        if ($search !== '') {
            $builder->like('l.title', $search);
        }

        $total = $builder->countAllResults(false);
        $builder->orderBy('m.id', 'DESC');

        if ($length > 0) {
            $builder->limit($length, $start);
        }

        $results = $builder->get()->getResult();

        return ['total' => $total, 'results' => $results];
    }

    /**
     * Reproduces the post-conversion CommonModel call exactly as it appears
     * in modules/Pages/Controllers/Pages.php:30-42.
     *
     * @param string $locale
     * @param string $search
     * @param int    $length
     * @param int    $start
     *
     * @return array
     */
    private function newCommonModelQuery(string $locale, string $search, int $length, int $start): array
    {
        $cm = new CommonModel();
        $joins = [['table' => 'pages_langs l', 'cond' => "l.pages_id = m.id AND l.lang = '{$locale}'", 'type' => 'left']];
        $like = $search !== '' ? ['l.title' => $search] : [];

        $total = $cm->lists('pages m', 'm.*, l.title, l.seflink', [], 'm.id DESC', 0, 0, $like, [], $joins, ['count' => true]);

        $limit = $length > 0 ? $length : 0;
        $offset = $length > 0 ? $start : 0;

        $results = $cm->lists('pages m', 'm.*, l.title, l.seflink', [], 'm.id DESC', $limit, $offset, $like, [], $joins);

        return ['total' => $total, 'results' => $results];
    }

    /**
     * Asserts the old and new query results are byte-for-byte identical.
     *
     * @param array{total: int, results: list<object>} $old
     * @param array                                     $new
     * @param string                                    $case
     *
     * @return void
     */
    private function assertSameShape(array $old, array $new, string $case): void
    {
        $this->assertSame($old['total'], $new['total'], "total mismatch for case: {$case}");
        $this->assertSame(count($old['results']), count($new['results']), "row count mismatch for case: {$case}");

        foreach ($old['results'] as $i => $oldRow) {
            $this->assertSame((array) $oldRow, (array) $new['results'][$i], "row #{$i} content mismatch for case: {$case}");
        }
    }

    /**
     * No search string, no limit: both queries must return every pages row
     * with the same left-joined title/seflink columns, in the same order,
     * and the same countAllResults(false) total.
     *
     * @return void
     */
    public function testNoSearchNoLimitMatchesOriginal(): void
    {
        $old = $this->oldRawQuery('tr', '', 0, 0);
        $new = $this->newCommonModelQuery('tr', '', 0, 0);
        $this->assertGreaterThanOrEqual(1, $old['total'], 'Fixture must have at least 1 pages row for this test to be meaningful.');
        $this->assertSameShape($old, $new, 'no search, no limit');
    }

    /**
     * limit()+offset(): CommonModel::lists() always calls
     * $builder->limit($limit, $pkCount), including for the count-only call,
     * but countAllResults() internally resets QBLimit/QBOrderBy before
     * running (BaseBuilder::countAllResults), so the total must be
     * unaffected by the paginated call's limit/offset.
     *
     * @return void
     */
    public function testWithLimitAndOffsetMatchesOriginal(): void
    {
        $old = $this->oldRawQuery('tr', '', 1, 0);
        $new = $this->newCommonModelQuery('tr', '', 1, 0);
        $this->assertSame(1, count($old['results']));
        $this->assertSameShape($old, $new, 'limit=1 offset=0');

        $this->assertGreaterThanOrEqual(2, $old['total'], 'Fixture must have at least 2 pages rows for offset=1 to be meaningful.');
        $old2 = $this->oldRawQuery('tr', '', 1, 1);
        $new2 = $this->newCommonModelQuery('tr', '', 1, 1);
        $this->assertSameShape($old2, $new2, 'limit=1 offset=1');
    }

    /**
     * Search string routed through like(): CommonModel's single-element
     * $like array must produce the same AND-like() as the original
     * ->like('l.title', $search) call.
     *
     * @return void
     */
    public function testWithSearchStringMatchesOriginal(): void
    {
        $old = $this->oldRawQuery('tr', 'leti', 0, 0);
        $new = $this->newCommonModelQuery('tr', 'leti', 0, 0);
        $this->assertGreaterThanOrEqual(1, count($old['results']), 'Fixture must contain a pages_langs.title matching the "leti" substring for this test to be meaningful.');
        $this->assertSameShape($old, $new, 'search=leti');
    }

    /**
     * Non-matching locale: the LEFT JOIN must still return every pages row,
     * with title/seflink = NULL, identically in the raw and CommonModel
     * queries.
     *
     * @return void
     */
    public function testNonMatchingLocaleMatchesOriginal(): void
    {
        $old = $this->oldRawQuery('xx', '', 0, 0);
        $new = $this->newCommonModelQuery('xx', '', 0, 0);
        $this->assertNotEmpty($new['results'], 'Fixture must have at least 1 pages row for this test to be meaningful.');
        $this->assertSameShape($old, $new, 'locale=xx (no match)');

        foreach ($new['results'] as $row) {
            $this->assertNull($row->title);
            $this->assertNull($row->seflink);
        }
    }
}
