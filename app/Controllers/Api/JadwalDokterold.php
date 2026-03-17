<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\JadwalDokterModel;

class JadwalDokter extends BaseController
{
    public function index()
    {
        // Ambil JSON body
        $payload = $this->request->getJSON(true);

        // fallback form-data
        if (!$payload) {
            $payload = $this->request->getPost();
        }

        $tgl = $payload['TGL'] ?? null;

        if (!$tgl) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Parameter TGL wajib (YYYY-MM-DD)'
            ]);
        }

        $model = new JadwalDokterModel();
        $data  = $model->getJadwalDokter($tgl);

        return $this->response->setJSON([
            'status' => 'success',
            'source' => 'oracle',
            'tgl'    => $tgl,
            'count'  => count($data),
            'data'   => $data
        ]);
    }
}
