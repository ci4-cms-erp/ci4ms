<?php

namespace Modules\Backend\Libraries;

use ci4commonmodel\CommonModel;

class CommonTagsLibrary
{
    /**
     * @var CommonModel
     */
    protected $commonModel;

    public function __construct()
    {
        $this->commonModel = new CommonModel();
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
        if ($isUpdate === true) $this->commonModel->remove('tags_pivot', ['piv_id' => $insertedID, 'tagType' => $type]);
        $jsons = json_decode($tags);
        foreach ($jsons as $item) {
            if (!empty($item->id)) {
                $value = strip_tags(trim($item->value));

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
                $value = strip_tags(trim($item->value));
                if (!empty($value)) {
                    $tag = $this->commonModel->selectOne('tags', ['tag' => $value]);
                    if (empty($tag) || $value != $tag->tag) {
                        $max_url_increment = 10000;
                        $link = null;
                        if ($this->commonModel->isHave($table, ['seflink' => seflink($value, ['lowercase'])]) === 0) $link = seflink($value, ['lowercase']);
                        else
                            for ($i = 1; $i <= $max_url_increment; $i++) {
                                $new_link = seflink($value, ['lowercase']) . '-' . $i;
                                if ($this->commonModel->isHave($table, ['seflink' => $new_link]) === 0) {
                                    $link = $new_link;
                                    break;
                                }
                            }
                        $addedTagID = $this->commonModel->create('tags', ['tag' => $value, 'seflink' => $link]);
                        $this->commonModel->create('tags_pivot', ['tag_id' => $addedTagID, 'tagType' => $type, 'piv_id' => $insertedID]);
                    } else {
                        $this->commonModel->create('tags_pivot', ['tag_id' => $tag->id, 'tagType' => $type, 'piv_id' => $insertedID]);
                    }
                }
            }
        }
    }
}
