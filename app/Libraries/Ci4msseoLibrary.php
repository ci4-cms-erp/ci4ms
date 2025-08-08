<?php

namespace App\Libraries;

use Melbahja\Seo\MetaTags;
use Melbahja\Seo\Schema;
use Melbahja\Seo\Schema\Thing;

/**
 * Class Ci4msseoLibrary
 *
 * Provides SEO functionality for CodeIgniter 4 applications.
 * Includes methods for generating meta tags and JSON-LD structured data.
 */
class Ci4msseoLibrary
{
    /**
     * Generates meta tags for SEO optimization.
     *
     * @param string $title The page title
     * @param string $description The page description
     * @param string $url The canonical URL
     * @param array $metatagsArray Additional meta tags (keywords, author, etc.)
     * @param string $coverImage Optional cover image URL
     * @param string $robots Robots meta tag value (default: 'index, follow')
     * @return MetaTags Configured MetaTags instance
     */
    public function metaTags($title, $description, string $url, array $metatagsArray = [], string $coverImage = '', $robots = 'index, follow')
    {
        $metatags = (new MetaTags())
            ->title($title)
            ->description($description)
            ->meta('robots', $robots);
        if (!empty($coverImage)) {
            $metatags->image($coverImage);
        }

        if (!empty($metatagsArray['keywords'])) {
            $keywords = implode(', ', $metatagsArray['keywords']);
            $metatags->meta('keywords', $keywords);
        }

        if (!empty($metatagsArray['author'])) {
            $metatags->meta('author', $metatagsArray['author']);
        }
        $metatags->canonical(site_url($url));

        return $metatags;
    }

    /**
     * Generates JSON-LD structured data for SEO.
     *
     * @param string $type The type of schema.org thing
     * @param array $data The data for the schema.org thing
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
     */
    public function ldPlusJson(string $type, array $data): Schema
    {
        if (!empty($data['children'])) {
            $children = array_reduce(array_keys($data['children']), function ($acc, $key) use ($data) {
                $childType = array_key_first($data['children'][$key]);
                $acc[lcfirst($key)] = new Thing($childType, $data['children'][$key][$childType]);
                return $acc;
            }, []);
            $data = array_merge($data, $children);
            unset($data['children']);
        }
        return new Schema(new Thing($type, $data));
    }
}
