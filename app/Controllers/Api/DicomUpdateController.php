<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\ResponseInterface;

class DicomUpdateController extends ResourceController
{
    private $orthancUrl = 'http://192.168.106.89:8042/';
    private $username   = 'orthanc';
    private $password   = 'orthanc';

    /**
     * Update PatientID pada sebuah Study di Orthanc.
     * Akan membuat salinan baru dengan PatientID baru.
     * 
     * Contoh request:
     * POST /api/dicom/update
     * {
     *   "studyId": "abc12345",
     *   "newPatientId": "P001-NEW"
     * }
     */
    public function updatePatientId()
    {
        $input = $this->request->getJSON(true);

        if (empty($input['studyId']) || empty($input['newPatientId'])) {
            return $this->failValidationError('Parameter "studyId" dan "newPatientId" wajib diisi.');
        }

        $studyId = $input['studyId'];
        $newPatientId = $input['newPatientId'];

        $url = $this->orthancUrl . 'tools/modify';
        $client = \Config\Services::curlrequest();

        $body = [
            'Replace' => [
                'PatientID' => $newPatientId
            ],
            'Resources' => [$studyId]
        ];

        try {
            $response = $client->request('POST', $url, [
                'auth' => [$this->username, $this->password],
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'json' => $body
            ]);

            $data = json_decode($response->getBody(), true);

            log_message('debug', 'Modify response: ' . print_r($data, true));

            return $this->respond([
                'message' => 'Berhasil membuat study baru dengan PatientID baru.',
                'originalStudyId' => $studyId,
                'newPatientId' => $newPatientId,
                'newStudyId' => $data['ID'] ?? null,
                'details' => $data
            ], ResponseInterface::HTTP_OK);

        } catch (\Exception $e) {
            log_message('error', 'Gagal update PatientID: ' . $e->getMessage());
            return $this->failServerError('Gagal memperbarui PatientID. Cek koneksi ke Orthanc.');
        }
    }
}
