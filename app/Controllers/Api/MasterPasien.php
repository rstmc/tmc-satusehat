<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\MasterPS;
use App\Services\SatusehatService;

class MasterPasien extends BaseController
{
    protected $service;

    public function __construct()
    {
        $this->service = new SatusehatService();
    }

    public function list()
    {
        $data = $this->request->getJSON(true);

        if (!$data && $this->request->getMethod() === 'post') {
             return $this->response->setStatusCode(400)->setJSON([
                'status'  => false,
                'message' => 'Request harus JSON'
            ]);
        }
        
        if (!$data) {
            $data = $this->request->getGet();
        }

        $patient = $data['patient'] ?? '';
        $rm = $data['rm'] ?? '';
        $ihs = $data['ihs'] ?? '';
        $limit   = (int) ($data['limit'] ?? 20);
        $offset  = (int) ($data['offset'] ?? 0);

        // Safety
        if ($limit <= 0 || $limit > 100) {
            $limit = 20;
        }
        if ($offset < 0) {
            $offset = 0;
        }

        $model = new MasterPS();
        
        try {
            $rows  = $model->searchPasien($patient, $rm, $ihs, $limit, $offset);
            $total = $model->countSearchPasien($patient, $rm, $ihs);
            
            return $this->response->setJSON([
                'status' => true,
                'source' => 'sqlserver',
                'meta'   => [
                    'limit'  => $limit,
                    'offset' => $offset,
                    'total'  => $total,
                    'page'   => floor($offset / $limit) + 1
                ],
                'data' => $rows
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getSatuSehatIHS($nik, $medrec)
    {
        if (empty($nik)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => false,
                'message' => 'NIK tidak boleh kosong'
            ]);
        }

        $name = $this->request->getGet('namapasien');

        try {
            $queryParams = [
                'identifier' => 'https://fhir.kemkes.go.id/id/nik|' . $nik
            ];

            if (!empty($name)) {
                $queryParams['name'] = $name;
            }

            $response = $this->service->get('Patient', $queryParams);
            
            if (isset($response['total']) && $response['total'] > 0 && !empty($response['entry'][0]['resource']['id'])) {
                $ihsId = $response['entry'][0]['resource']['id'];
                
                $model = new MasterPS();
                $model->updateIHSByNik($nik, $medrec, $ihsId);
            }

            return $this->response->setJSON([
                'status' => true,
                'data'   => $response,
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => false,
                'message' => $e->getMessage(),
            ]);
        }

        
    }

}
