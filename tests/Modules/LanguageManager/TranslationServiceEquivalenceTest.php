<?php

declare(strict_types=1);

namespace Tests\Modules\LanguageManager;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use ci4commonmodel\CommonModel;
use Modules\LanguageManager\Libraries\TranslationService;

/**
 * Equivalence regression tests for the TranslationService methods converted
 * from raw db_connect() query-builder chains to CommonModel calls (see
 * modules/LanguageManager/Libraries/TranslationService.php).
 *
 * Each test exercises the real, converted TranslationService method (not a
 * re-implementation) and asserts the behavior the original raw SQL
 * guaranteed: update-in-place instead of duplicate inserts, UNIQUE-key
 * idempotency, DISTINCT+ordered group listing, and single-default
 * enforcement. Rows created by a test are self-created under a unique
 * marker and removed in tearDown(); the shared languages.is_default
 * fixture state is snapshotted and restored in a finally block, per
 * .ci4ms/knowledge/pitfalls.md ("DatabaseTestTrait testleri transaction'a
 * sarmıyor").
 */
final class TranslationServiceEquivalenceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = false;
    protected $namespace = null;

    private const GROUP = '__qa_translation_equivalence_group__';
    private const KEY   = '__qa_translation_equivalence_key__';

    protected function tearDown(): void
    {
        parent::tearDown();
        // FK CASCADE (translations.key_id -> translation_keys.id) also
        // removes any leftover translations rows created under this group.
        (new CommonModel())->remove('translation_keys', ['group_name' => self::GROUP]);
    }

    /**
     * addKey() must be idempotent under the (group_name, key_name) UNIQUE
     * key: a second call with the same group/key returns the existing
     * row's id instead of raising a duplicate-key error or inserting a
     * second row.
     *
     * @return void
     */
    public function testAddKeyIsIdempotentUnderUniqueConstraint(): void
    {
        $service = new TranslationService();

        $id1 = $service->addKey(self::GROUP, self::KEY);
        $id2 = $service->addKey(self::GROUP, self::KEY);

        $this->assertNotNull($id1);
        $this->assertSame($id1, $id2, 'addKey() must return the existing row id on the second call, not insert a duplicate.');

        $cm = new CommonModel();
        $count = $cm->count('translation_keys', ['group_name' => self::GROUP, 'key_name' => self::KEY]);
        $this->assertSame(1, $count, 'Exactly one row must exist after two addKey() calls with the same group/key.');
    }

    /**
     * saveTranslation() must UPDATE the existing (key_id, language_code)
     * row in place on a second call, matching the pre-conversion
     * select-then-insert-or-update logic: same row id, no duplicate row.
     *
     * @return void
     */
    public function testSaveTranslationInsertsThenUpdatesInPlace(): void
    {
        $service = new TranslationService();
        $cm = new CommonModel();

        $keyId = $service->addKey(self::GROUP, self::KEY);
        if ($keyId === null) {
            $this->fail('addKey() must return an id for a freshly created key.');
        }

        $service->saveTranslation($keyId, 'tr', 'v1');
        $row = $cm->selectOne('translations', ['key_id' => $keyId, 'language_code' => 'tr']);
        if ($row === null) {
            $this->fail('Expected a translations row after the first saveTranslation() call.');
        }
        $this->assertSame('v1', $row->value);
        $firstRowId = $row->id;

        $service->saveTranslation($keyId, 'tr', 'v2');
        $countAfterSecondSave = $cm->count('translations', ['key_id' => $keyId, 'language_code' => 'tr']);
        $this->assertSame(1, $countAfterSecondSave, 'Second saveTranslation() call must UPDATE the existing row, not INSERT a duplicate.');

        $row = $cm->selectOne('translations', ['key_id' => $keyId, 'language_code' => 'tr']);
        if ($row === null) {
            $this->fail('Expected a translations row after the second saveTranslation() call.');
        }
        $this->assertSame('v2', $row->value);
        $this->assertSame($firstRowId, $row->id, 'saveTranslation() must update the same row id, not open a new one.');
    }

    /**
     * getGroups() must return DISTINCT group names ordered by group_name
     * ASC, matching the original ->distinct()->orderBy('group_name') chain.
     *
     * @return void
     */
    public function testGetGroupsReturnsDistinctSortedGroups(): void
    {
        $cm = new CommonModel();
        $cm->create('translation_keys', ['group_name' => self::GROUP, 'key_name' => 'key_a']);
        $cm->create('translation_keys', ['group_name' => self::GROUP, 'key_name' => 'key_b']);

        $service = new TranslationService();
        $groups = $service->getGroups();

        $names = array_map(static fn ($g) => $g->group_name, $groups);
        $occurrences = array_count_values($names);

        $this->assertContains(self::GROUP, $names);
        $this->assertSame(1, $occurrences[self::GROUP], 'DISTINCT must collapse the two rows sharing the same group_name into one.');

        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names, "getGroups() must be sorted by group_name ASC.");
    }

    /**
     * setDefault() must enforce exactly one default language row: every
     * row is reset to is_default=0 then only the given id is set to 1,
     * matching the original two-statement update-all-then-set-one raw SQL.
     *
     * @return void
     */
    public function testSetDefaultEnforcesSingleDefault(): void
    {
        $cm = new CommonModel();
        $snapshot = $cm->lists('languages', 'id, is_default');

        try {
            $service = new TranslationService();
            $ids = array_map(static fn ($r) => (int) $r->id, $snapshot);
            $this->assertGreaterThanOrEqual(2, count($ids), 'Fixture needs at least 2 language rows for this check to be meaningful.');

            [$first, $second] = $ids;

            $service->setDefault($first);
            $this->assertSame(1, $cm->count('languages', ['id' => $first, 'is_default' => 1]));
            $this->assertSame(0, $cm->count('languages', ['is_default' => 1, 'id !=' => $first]), 'Only one row may have is_default=1 after setDefault().');

            $service->setDefault($second);
            $this->assertSame(1, $cm->count('languages', ['id' => $second, 'is_default' => 1]));
            $this->assertSame(0, $cm->count('languages', ['is_default' => 1, 'id !=' => $second]), 'setDefault() must flip the previous default off (no-where update-all path).');
        } finally {
            foreach ($snapshot as $row) {
                $cm->edit('languages', ['is_default' => (int) $row->is_default], ['id' => $row->id]);
            }
        }
    }
}
