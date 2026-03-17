<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class DicomUpdateByPatientController extends ResourceController
{
    private $orthancUrl = 'http://192.168.106.89:8042/';
    private $auth;

    public function __construct()
    {
        $this->auth = [
            'auth' => ['orthanc', 'orthanc']
        ];
    }

    public function updateByPatientId()
    {
        $input = $this->request->getJSON(true);

        // 1. Validasi Input
        if (empty($input['oldPatientId']) || empty($input['newPatientId'])) {
            return $this->fail('oldPatientId & newPatientId wajib diisi');
        }

        $oldPatientId = trim($input['oldPatientId']);
        $newPatientId = trim($input['newPatientId']);

        if (empty($oldPatientId) || empty($newPatientId)) {
            return $this->fail('PatientID tidak boleh kosong/hanya spasi');
        }

        $client = \Config\Services::curlrequest();

        try {
            // --- 1. CARI UUID PASIEN ---
            $listResponse = $client->get($this->orthancUrl . 'patients', array_merge($this->auth, ['http_errors' => false]));
            $patients = json_decode($listResponse->getBody(), true);
            
            if ($listResponse->getStatusCode() != 200) {
                return $this->failServerError("Gagal koneksi ke Orthanc: " . $listResponse->getBody());
            }

            $patientUuid = null;

            foreach ($patients as $uuid) {
                // Gunakan ?expand untuk mendapatkan tag tanpa request ulang
                $detailResponse = $client->get($this->orthancUrl . "patients/$uuid?expand", array_merge($this->auth, ['http_errors' => false]));
                $detail = json_decode($detailResponse->getBody(), true);

                if (($detail['MainDicomTags']['PatientID'] ?? '') === $oldPatientId) {
                    $patientUuid = $uuid;
                    break;
                }
            }

            if (!$patientUuid) {
                return $this->failNotFound("PatientID $oldPatientId tidak ditemukan");
            }

            // --- 2. MODIFY ---
            $modifyUrl = $this->orthancUrl . "patients/$patientUuid/modify";
            
            // Payload Modify
            $payload = [
                'json' => [
                    'Force' => true, // <--- INI KUNCI PENTING YANG KURANG
                    'Replace' => [
                        'PatientID' => $newPatientId
                    ]
                ],
                'http_errors' => false 
            ];

            $modifyResponse = $client->post($modifyUrl, array_merge($this->auth, $payload));
            $statusCode = $modifyResponse->getStatusCode();
            $responseBody = $modifyResponse->getBody();

            // Cek Status Code
            if ($statusCode !== 200) {
                log_message('error', "Orthanc Modify Error: $statusCode - $responseBody");
                
                return $this->fail([
                    'status' => $statusCode,
                    'orthanc_error_message' => json_decode($responseBody, true),
                    'plain_text' => $responseBody
                ], $statusCode);
            }

            // Parsing Hasil
            $result = json_decode($responseBody, true);
            $newPatientUuid = $result['ID'] ?? null;

            if (!$newPatientUuid) {
                return $this->fail('Gagal parsing hasil modifikasi (ID baru tidak ditemukan)');
            }

            // --- 3. HAPUS PASIEN LAMA ---
            $deleteResponse = $client->delete($this->orthancUrl . "patients/$patientUuid", array_merge($this->auth, ['http_errors' => false]));
            
            return $this->respond([
                'status'          => 'success',
                'message'         => 'PatientID berhasil diperbarui dan data lama dihapus',
                'newPatientId'    => $newPatientId,
                'newPatientUuid'  => $newPatientUuid,
                'deletedPatientUuid' => $patientUuid
            ]);

        } catch (\Throwable $e) {
            log_message('error', $e->getMessage());
            return $this->failServerError('Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }
}