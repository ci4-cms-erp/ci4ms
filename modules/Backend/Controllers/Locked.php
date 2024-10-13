<?php

namespace Modules\Backend\Controllers;

use CodeIgniter\I18n\Time;
use JasonGrimes\Paginator;

class Locked extends BaseController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $filterData = [];
        if (!empty($this->request->getGet())) {
            $clearData = clearFilter($this->request->getGet());
            $dates = explode(" - ", $clearData['date_range']);

            $locked_at = Time::createFromFormat('Y-m-d H:i:s', new Time($dates[0]), 'Europe/Istanbul')->toLocalizedString();
            $expiry_date = Time::createFromFormat('Y-m-d H:i:s', new Time($dates[1]), 'Europe/Istanbul')->toLocalizedString();

            $filterData = [
                'isLocked' => (isset($clearData['status'])) ? (bool)$clearData['status'] : null,
                'locked_at' => ['$gte' => $locked_at],
                'expiry_date' => ['$lte' => $expiry_date],
            ];
            $filterData = clearFilter($filterData);
        }
        $totalItems = $this->commonModel->count('locked', $filterData);
        $itemsPerPage = 10;
        $currentPage = $this->request->getUri()->getSegment(3, 1);
        $urlPattern = '/backend/locked/(:num)';
        $paginator = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
        $paginator->setMaxPagesToShow(5);
        $bpk = ($this->request->getUri()->getSegment(3, 1) - 1) * $itemsPerPage;

        //dd($clearData);
        $this->defData = array_merge($this->defData, [
            'paginator' => $paginator,
            'locks' => $this->commonModel->lists('locked','*', $filterData,'id ASC', $itemsPerPage,$bpk,['username' => (isset($clearData['email'])) ? $clearData['email']: null,
                'ip_address' => (isset($clearData['ip'])) ?$clearData['ip'] : null]),
            'totalCount' => $totalItems,
            'filteredData' => $clearData ?? null,
        ]);
        return view('Modules\Backend\Views\logs\locked', $this->defData);
    }
}