<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\RadiologiSqlsrvModel;

class Radiologi extends BaseController
{
    public function index()
    {
        $data = $this->request->getJSON(true);

        if (!$data) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Request harus JSON'
            ]);
        }

        $startDate = $data['start'] ?? null;
        $endDate   = $data['end'] ?? null;
        $limit     = (int) ($data['limit'] ?? 20);
        $offset    = (int) ($data['offset'] ?? 0);

        // Safety
        if ($limit <= 0 || $limit > 100) {
            $limit = 20;
        }
        if ($offset < 0) {
            $offset = 0;
        }

        $model = new RadiologiSqlsrvModel();
        $rows  = $model->getRadiologi($startDate, $endDate, $limit, $offset);
        $total = $model->countRadiologi($startDate, $endDate);

        return $this->response->setJSON([
            'status' => 'success',
            'source' => 'sqlserver',
            'meta'   => [
                'limit'  => $limit,
                'offset' => $offset,
                'total'  => $total,
                'page'   => floor($offset / $limit) + 1
            ],
            'data' => $rows
        ]);
    }
}
