<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Backend\Libraries\CommonTagsLibrary;

/**
 * CommonTagsLibrary::checkTags() behaviour lock.
 *
 * CommonModel is mocked (checkTags' default `new CommonModel()` would open the
 * *default* group = the live database), so nothing here touches a real DB.
 * The mock is injected through the constructor.
 *
 * Covers the two things the phase-4 pass changed:
 *   1. Invalid/empty JSON is now a no-op -- previously it deleted the item's
 *      pivots (on update) and then fataled on foreach(null): data loss + crash.
 *   2. New-tag slug collisions resolve from a single lists() query
 *      (firstFreeSeflink) instead of up to 10,000 isHave() probes, and pick the
 *      same lowest free suffix.
 *
 * @internal
 */
final class CommonTagsLibraryTest extends CIUnitTestCase
{
    /** @return \ci4commonmodel\CommonModel&\PHPUnit\Framework\MockObject\MockObject */
    private function model(): object
    {
        return $this->getMockBuilder(\ci4commonmodel\CommonModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['remove', 'selectOne', 'create', 'edit', 'lists'])
            ->getMock();
    }

    // ── Invalid / empty payloads: must be a no-op (bug fix) ─────────────

    public function testInvalidJsonDoesNothingAndDoesNotThrow(): void
    {
        $mock = $this->model();
        $mock->expects($this->never())->method('remove');
        $mock->expects($this->never())->method('create');
        $mock->expects($this->never())->method('edit');
        $mock->expects($this->never())->method('selectOne');

        (new CommonTagsLibrary($mock))->checkTags('{not valid json}', 'blog', '1');
        $this->assertTrue(true); // reached here => no exception
    }

    /** The core regression: a malformed payload must NOT wipe existing pivots. */
    public function testInvalidJsonWithUpdateDoesNotDeletePivots(): void
    {
        $mock = $this->model();
        $mock->expects($this->never())->method('remove');
        $mock->expects($this->never())->method('create');

        (new CommonTagsLibrary($mock))->checkTags('not-json', 'blog', '7', 'pages', true);
        $this->assertTrue(true);
    }

    public function testEmptyStringDoesNothing(): void
    {
        $mock = $this->model();
        $mock->expects($this->never())->method('remove');
        $mock->expects($this->never())->method('create');

        (new CommonTagsLibrary($mock))->checkTags('', 'blog', '1', 'pages', true);
        $this->assertTrue(true);
    }

    public function testEmptyArrayIsHarmless(): void
    {
        $mock = $this->model();
        $mock->expects($this->never())->method('create');
        // isUpdate defaults to false, so no remove either.
        $mock->expects($this->never())->method('remove');

        (new CommonTagsLibrary($mock))->checkTags('[]', 'blog', '1');
        $this->assertTrue(true);
    }

    public function testEmptyArrayWithUpdateStillClearsPivots(): void
    {
        $mock = $this->model();
        $mock->expects($this->once())->method('remove')
            ->with('tags_pivot', ['piv_id' => '5', 'tagType' => 'blog']);
        $mock->expects($this->never())->method('create');

        (new CommonTagsLibrary($mock))->checkTags('[]', 'blog', '5', 'pages', true);
    }

    // ── New tag creation + slug collision ──────────────────────────────

    public function testNewTagUsesBaseSeflinkWhenFree(): void
    {
        $mock = $this->model();
        $mock->method('selectOne')->willReturn(null);      // tag does not exist
        $mock->method('lists')->willReturn([]);            // no seflink taken
        $created = [];
        $mock->method('create')->willReturnCallback(function ($table, $data) use (&$created) {
            $created[] = [$table, $data];
            return count($created);
        });

        (new CommonTagsLibrary($mock))->checkTags(json_encode([['id' => '', 'value' => 'Hello World']]), 'blog', '1');

        $this->assertSame('tags', $created[0][0]);
        $this->assertSame('Hello World', $created[0][1]['tag']);
        $this->assertSame('hello-world', $created[0][1]['seflink']);
        $this->assertSame('tags_pivot', $created[1][0]);
    }

    public function testNewTagSlugCollisionPicksNextFreeSuffix(): void
    {
        $mock = $this->model();
        $mock->method('selectOne')->willReturn(null);
        // 'hello-world' and 'hello-world-1' already taken -> expect -2.
        $mock->method('lists')->willReturn([
            (object) ['seflink' => 'hello-world'],
            (object) ['seflink' => 'hello-world-1'],
        ]);
        $created = [];
        $mock->method('create')->willReturnCallback(function ($table, $data) use (&$created) {
            $created[] = [$table, $data];
            return count($created);
        });

        (new CommonTagsLibrary($mock))->checkTags(json_encode([['id' => '', 'value' => 'Hello World']]), 'blog', '1');

        $this->assertSame('hello-world-2', $created[0][1]['seflink']);
    }

    public function testExistingTagReusedWithoutCreatingIt(): void
    {
        $mock = $this->model();
        $mock->method('selectOne')->willReturn((object) ['id' => 42, 'tag' => 'php']);
        $createdTables = [];
        $mock->method('create')->willReturnCallback(function ($table, $data) use (&$createdTables) {
            $createdTables[] = $table;
            return 1;
        });

        (new CommonTagsLibrary($mock))->checkTags(json_encode([['id' => '', 'value' => 'php']]), 'blog', '1');

        // Only a pivot row, never a new tag row.
        $this->assertSame(['tags_pivot'], $createdTables);
    }

    public function testHtmlIsStrippedFromNewTagValue(): void
    {
        $mock = $this->model();
        $mock->method('selectOne')->willReturn(null);
        $mock->method('lists')->willReturn([]);
        $created = [];
        $mock->method('create')->willReturnCallback(function ($table, $data) use (&$created) {
            $created[] = [$table, $data];
            return count($created);
        });

        (new CommonTagsLibrary($mock))->checkTags(json_encode([['id' => '', 'value' => '<b>bold</b>tag']]), 'blog', '1');

        $this->assertSame('boldtag', $created[0][1]['tag']);
    }

    public function testUpdateRemovesOldPivotsBeforeReprocessing(): void
    {
        $mock = $this->model();
        $mock->expects($this->once())->method('remove')
            ->with('tags_pivot', ['piv_id' => '9', 'tagType' => 'blog']);
        $mock->method('selectOne')->willReturn((object) ['id' => 3, 'tag' => 'news']);
        $mock->method('create')->willReturn(1);

        (new CommonTagsLibrary($mock))->checkTags(json_encode([['id' => '', 'value' => 'news']]), 'blog', '9', 'pages', true);
    }
}
