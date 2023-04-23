<?php

namespace App\Libraries;

use Melbahja\Seo\MetaTags;
use Melbahja\Seo\Schema;
use Melbahja\Seo\Schema\Thing;

class Ci4msseoLibrary
{
    /**
     * @param $title
     * @param $description
     * @param string $url
     * @param array $metatags
     * @param string $coverImage
     * @return MetaTags
     */
    public function metaTags($title, $description, string $url, array $metatagsArray = [], string $coverImage = '')
    {
        $metatags = new MetaTags();
        $metatags->title($title);
        $metatags->description($description);
        if (!empty($coverImage)) $metatags->image($coverImage);
        if (is_array($metatagsArray['keywords']) && !empty($metatagsArray['keywords'])) {
            $keywords = '';
            foreach ($metatagsArray['keywords'] as $tag) {
                $keywords .= $tag . ', ';
            }
            $metatags->meta('keywords', substr($keywords, 0, -2));
        }
        if (!empty($metatagsArray['author'])) $metatags->meta('author', $metatagsArray['author']);
        $metatags->canonical(site_url($url));
        return $metatags;
    }

    /**
     * @param string $type
     * @param array $data
     * The arrangement of the data to be added to the data array should be as follows
     *        [
     *            'url' => 'https://ci4ms/blog/test',
     *            'logo' => 'https://ci4ms/uploads/media/logo.png',
     *            'name' => 'Ci4MS',
     *            'headline' => 'test',
     *            'image' => 'https://kun-cms/uploads/media/main-vector.png',
     *            'description' => 'test',
     *            'datePublished' => '2023-04-17T15:43:23+00:00',
     *            'children' =>
     *                [
     *                    'mainEntityOfPage' => // will be $type attribute merging
     *                        [// sub data
     *                            'WebPage' => []
     *
     *                        ],
     *
     *                    'ContactPoint' => // will be $type attribute merging
     *                        [// sub data
     *                            'ContactPoint' =>
     *                                [
     *                                    'telephone' => '+905469612939',
     *                                    'contactType' => 'customer support'
     *                                ]
     *
     *                        ],
     *                      ...
     *                ],
     *
     *            'sameAs' =>
     *                [
     *                    'https://facebook.com/bertugfahriozer',
     *                    'https://twitter.com/bertugfahriozer',
     *                    'https://github.com/bertugfahriozer.com'
     *                ],
     *              ...
     *        ]
     * @return Schema
     *
     */
    public function ldPlusJson(string $type, array $data)
    {
        if (!empty($data['children'])) {
            $data = array_merge($data, array_reduce(array_keys($data['children']), function ($acc, $key) use ($data) {
                $acc[lcfirst($key)] = new Thing(array_key_first($data['children'][$key]), $data['children'][$key][array_key_first($data['children'][$key])]);
                return $acc;
            }, []));
            unset($data['children']);
        }
        return new Schema(new Thing($type, $data));
    }
}