<?php

declare(strict_types=1);

namespace Modules\Backend\Libraries;

use ci4commonmodel\CommonModel;

class CommonTagsLibrary
{
    /**
     * @var CommonModel
     */
    protected $commonModel;

    public function __construct(?CommonModel $commonModel = null)
    {
        $this->commonModel = $commonModel ?? new CommonModel();
    }

    /**
     * @param string $tags
     * @param string $type
     * @param string $insertedID
     * @param string $tablename
     * @param bool $isUpdate
     * @throws \Exception
     */
    public function checkTags(string $tags, string $type, string $insertedID, string $table = 'pages', bool $isUpdate = false)
    {
        $jsons = json_decode($tags);
        // Invalid/empty payload: bail out BEFORE deleting anything, otherwise a
        // malformed POST would remove the item's existing tag links (isUpdate)
        // and then fatal on foreach(null) -- data loss plus a crash.
        if (!is_array($jsons) && !is_object($jsons)) {
            return;
        }

        if ($isUpdate === true) $this->commonModel->remove('tags_pivot', ['piv_id' => $insertedID, 'tagType' => $type]);

        foreach ($jsons as $item) {
            if (!empty($item->id)) {
                $value = strip_tags(trim((string) ($item->value ?? '')));

                if (!empty($value)) {
                    $tag = $this->commonModel->selectOne('tags', ['tag' => $value]);
                    if (!empty($tag) && $item->value == $tag->tag) {
                        $this->commonModel->create('tags_pivot', ['tag_id' => $tag->id, 'tagType' => $type, 'piv_id' => $insertedID]);
                    } else {
                        $this->commonModel->edit('tags', ['tag' => strip_tags(trim($value)), 'seflink' => seflink($value, ['lowercase'])], ['id' => $item->id]);
                        $this->commonModel->create('tags_pivot', ['tag_id' => $item->id, 'tagType' => $type, 'piv_id' => $insertedID]);
                    }
                }
            } else {
                $value = strip_tags(trim((string) ($item->value ?? '')));
                if (!empty($value)) {
                    $tag = $this->commonModel->selectOne('tags', ['tag' => $value]);
                    if (empty($tag) || $value != $tag->tag) {
                        $link = $this->firstFreeSeflink($table, seflink($value, ['lowercase']));
                        $addedTagID = $this->commonModel->create('tags', ['tag' => $value, 'seflink' => $link]);
                        $this->commonModel->create('tags_pivot', ['tag_id' => $addedTagID, 'tagType' => $type, 'piv_id' => $insertedID]);
                    } else {
                        $this->commonModel->create('tags_pivot', ['tag_id' => $tag->id, 'tagType' => $type, 'piv_id' => $insertedID]);
                    }
                }
            }
        }
    }

    /**
     * First unused seflink for $base under $table -- $base itself if free, else
     * $base-1, $base-2, ... -- resolved from a single query instead of one
     * COUNT per candidate (the old loop probed up to 10,000 times). Returns the
     * lowest free suffix, identical to sequential probing. The LIKE fetch may
     * return unrelated seflinks that merely contain $base; only exact "$base"
     * and "$base-<n>" keys are consulted, so those are harmless.
     */
    private function firstFreeSeflink(string $table, string $base): string
    {
        $taken = [];
        foreach ($this->commonModel->lists($table, 'seflink', [], 'id ASC', 0, 0, ['seflink' => $base]) as $row) {
            $taken[$row->seflink] = true;
        }

        if (!isset($taken[$base])) {
            return $base;
        }

        $i = 1;
        while (isset($taken[$base . '-' . $i])) {
            $i++;
        }

        return $base . '-' . $i;
    }
}
