<?php

namespace Modules\Backend\Libraries;

class CommonBackendLibrary
{
    public function buildSeoData($data): ?string
    {
        $seo = [];

        $pageimg = strip_tags(trim($data['pageimg'] ?? ''));
        if (!empty($pageimg)) {
            $seo['coverImage'] = $pageimg;
            $seo['IMGWidth']   = strip_tags(trim($data['pageIMGWidth'] ?? ''));
            $seo['IMGHeight']  = strip_tags(trim($data['pageIMGHeight'] ?? ''));
        }

        $description = strip_tags(trim($data['description'] ?? ''));
        if (!empty($description)) {
            $seo['description'] = $description;
        }

        $keywords = $data['keywords'] ?? '';
        if (!empty($keywords)) {
            $decoded = json_decode($keywords);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $keyword) {
                    $value = strip_tags(trim($keyword->value ?? ''));
                    if (empty($value)) unset($decoded[$key]);
                }
                $seo['keywords'] = array_values($decoded);
            }
        }

        return !empty($seo) ? json_encode($seo, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * Parse array of DataTables AJAX POST data uniformly.
     */
    public function getDatatablesPagination(array $postData): array
    {
        $data = clearFilter($postData);
        $searchString = trim(strip_tags($data['search']['value'] ?? ''));

        $length = isset($data['length']) ? (int)$data['length'] : 10;
        $start = isset($data['start']) ? (int)$data['start'] : 0;

        return [
            'length'       => ($length == -1) ? 0 : $length,
            'start'        => ($length == -1) ? 0 : $start,
            'searchString' => $searchString,
            'draw'         => isset($data['draw']) ? (int)$data['draw'] : 1
        ];
    }
}
