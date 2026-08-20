<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\PsriM;

class PsriController extends BaseController
{
    public function getAllPsri()
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

        $tanggal = $data['tanggal'] ?? '';

        if (empty($tanggal)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => false,
                'message' => 'Parameter tanggal diperlukan'
            ]);
        }

        $model = new PsriM();

        try {
            $result = $model->getAllPsri($tanggal);

            return $this->response->setJSON([
                'status' => true,
                'data'   => $result
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ]);
        }
    }

        public function getPsri()
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

        $noreg = $data['noreg'] ?? '';

        if (empty($noreg)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => false,
                'message' => 'Parameter Noreg diperlukan'
            ]);
        }

        $model = new PsriM();

        try {
            $result = $model->getPsri($noreg);

            return $this->response->setJSON([
                'status' => true,
                'data'   => $result
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ]);
        }
    }
}