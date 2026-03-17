<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\JadwalDokterModel;

class JadwalDokter extends BaseController
{
    public function index()
    {
        $payload = $this->request->getJSON(true);

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

        // Convert BLOB PHOTO ke Base64
        // foreach ($data as &$row) {
        //     if (isset($row['PHOTO'])) {
        //         if (is_object($row['PHOTO'])) {
        //             $row['PHOTO_BASE64'] = base64_encode($row['PHOTO']->load());
        //         } elseif (is_string($row['PHOTO'])) {
        //             $row['PHOTO_BASE64'] = base64_encode($row['PHOTO']);
        //         } else {
        //             $row['PHOTO_BASE64'] = null;
        //         }
        //         unset($row['PHOTO']);
        //     }
        // }

        return $this->response->setJSON([
            'status' => 'success',
            'source' => 'oracle',
            'tgl'    => $tgl,
            'count'  => count($data),
            'data'   => $data
        ]);
    }
}
