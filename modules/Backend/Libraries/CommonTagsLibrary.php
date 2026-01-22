<?php

namespace Modules\Backend\Libraries;

use ci4commonmodel\Models\CommonModel;

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
     * @param bool $isUpdate
     * @throws \Exception
     */
    public function checkTags(string $tags, string $type, string $insertedID, string $table = 'pages', bool $isUpdate = false)
    {
        if ($isUpdate === true) $this->commonModel->remove('tags_pivot', ['piv_id' => $insertedID, 'tagType' => $type]);
        $jsons = json_decode($tags);
        foreach ($jsons as $item) {
            if (!empty($item->id)) {
                $tag = $this->commonModel->selectOne('tags', ['tag' => $item->value]);
                if ($item->value == $tag->tag) {
                    $this->commonModel->create('tags_pivot', ['tag_id' => $tag->id, 'tagType' => $type, 'piv_id' => $insertedID]);
                }
                else {
                    $this->commonModel->edit('tags', ['tag' => $item->value, 'seflink' => seflink($item->value, ['lowercase'])], ['id' => $item->id]);
                    $this->commonModel->create('tags_pivot', ['tag_id' => $item->id, 'tagType' => $type, 'piv_id' => $insertedID]);
                }
            } else {
                $tag = $this->commonModel->selectOne('tags', ['tag' => $item->value]);
                if (empty($tag) || $item->value != $tag->tag) {
                    $max_url_increment = 10000;
                    $link = null;
                    if ($this->commonModel->isHave($table, ['seflink' => seflink($item->value, ['lowercase'])]) === 0) $link = seflink($item->value, ['lowercase']);
                    else
                        for ($i = 1; $i <= $max_url_increment; $i++) {
                            $new_link = seflink($item->value, ['lowercase']) . '-' . $i;
                            if ($this->commonModel->isHave($table, ['seflink' => $new_link]) === 0) {
                                $link = $new_link;
                                break;
                            }
                        }
                    $addedTagID = $this->commonModel->create('tags', ['tag' => $item->value, 'seflink' => $link]);
                    $this->commonModel->selectOne('tags_pivot', ['tag_id' => $addedTagID, 'tagType' => $type, 'piv_id' => $insertedID]);
                } else {
                    $tag = $this->commonModel->selectOne('tags', ['tag' => $item->value]);
                    $this->commonModel->create('tags_pivot', ['tag_id' => $tag->id, 'tagType' => $type, 'piv_id' => $insertedID]);
                }
            }
        }
    }
}
