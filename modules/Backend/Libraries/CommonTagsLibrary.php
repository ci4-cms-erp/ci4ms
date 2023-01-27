<?php

namespace Modules\Backend\Libraries;

use ci4mongodblibrary\Models\CommonModel;
use MongoDB\BSON\ObjectId;

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
    public function checkTags(string $tags, string $type, string $insertedID,string $table='pages', bool $isUpdate = false)
    {
        if ($isUpdate===true)
            $this->commonModel->deleteMany('tags_pivot', ['piv_id' => new ObjectId($insertedID), 'tagType' => 'page']);
        $jsons = json_decode($tags);
        foreach ($jsons as $item) {
            if (!empty($item->id)) {
                $tag = $this->commonModel->getOne('tags', ['tag' => $item->value]);
                if ($item->value == $tag->tag)
                    $this->commonModel->createOne('tags_pivot', ['tag_id' => new ObjectId($item->id), 'tagType' => $type, 'piv_id' => new ObjectId($insertedID)]);
                else {
                    $this->commonModel->updateOne('tags', ['_id' => new ObjectId($item->id)], ['tag' => $item->value, 'seflink' => seflink($item->value, ['lowercase'])]);
                    $this->commonModel->createOne('tags_pivot', ['tag_id' => new ObjectId($item->id), 'tagType' => $type, 'piv_id' => new ObjectId($insertedID)]);
                }
            } else {
                $tag = $this->commonModel->getOne('tags', ['tag' => $item->value]);
                if (empty($tag) || $item->value != $tag->tag) {
                    $max_url_increment = 10000;
                    $link = null;
                    if ($this->commonModel->get_where(['seflink' => seflink($item->value, ['lowercase'])], $table) === 0)
                        $link = seflink($item->value, ['lowercase']);
                    else
                        for ($i = 1; $i <= $max_url_increment; $i++) {
                            $new_link = seflink($item->value, ['lowercase']) . '-' . $i;
                            if ($this->commonModel->get_where(['seflink' => $new_link], $table) === 0) {
                                $link = $new_link;
                                break;
                            }
                        }
                    $addedTagID = $this->commonModel->createOne('tags', ['tag' => $item->value, 'seflink' => $link]);
                    $this->commonModel->createOne('tags_pivot', ['tag_id' => new ObjectId($addedTagID), 'tagType' => 'page', 'piv_id' => new ObjectId($insertedID)]);
                }else{
                    $tag = $this->commonModel->getOne('tags', ['tag' => $item->value]);
                    $this->commonModel->createOne('tags_pivot', ['tag_id' => new ObjectId($tag->_id), 'tagType' => 'page', 'piv_id' => new ObjectId($insertedID)]);
                }
            }
        }
    }
}