<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class DicomSendController extends ResourceController
{
    private $orthancUrl = 'http://192.168.106.89:8042/';
    private $auth;

    // Nama modality sesuai orthanc.json
    private $targetModality = 'SatuSehatRouter';

    public function __construct()
    {
        $this->auth = [
            'auth' => ['orthanc', 'orthanc']
        ];
    }

    public function sendByPatientId()
    {
        $input = $this->request->getJSON(true);

        // 1. Validasi Input
        if (empty($input['patientId'])) {
            return $this->fail('patientId wajib diisi');
        }

        $patientId = trim($input['patientId']);

        if (empty($patientId)) {
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
                $detailResponse = $client->get($this->orthancUrl . "patients/$uuid?expand", array_merge($this->auth, ['http_errors' => false]));
                $detail = json_decode($detailResponse->getBody(), true);

                if (($detail['MainDicomTags']['PatientID'] ?? '') === $patientId) {
                    $patientUuid = $uuid;
                    break;
                }
            }

            if (!$patientUuid) {
                return $this->failNotFound("PatientID $patientId tidak ditemukan");
            }

            // --- 2. KIRIM KE SATUSEHAT ROUTER ---
            // Endpoint: POST /modalities/{modality}/store
            // Body: Array of UUIDs (Patient, Study, Series, atau Instance)
            $storeUrl = $this->orthancUrl . "modalities/" . $this->targetModality . "/store";

            $payload = [
                'json' => [$patientUuid], // Kirim UUID Patient
                'http_errors' => false 
            ];

            $storeResponse = $client->post($storeUrl, array_merge($this->auth, $payload));
            $statusCode = $storeResponse->getStatusCode();
            $responseBody = $storeResponse->getBody();

            // Orthanc mengembalikan 200 OK jika perintah dikirim (Queued)
            // Catatan: 200 di sini berarti Orthanc menerima perintah, BUKAN berarti SatuSehat 
            // sudah 100% selesai memproses file (asynchronous), tapi ini indikasi berhasil kirim.
            if ($statusCode !== 200) {
                log_message('error', "Orthanc Store Error: $statusCode - $responseBody");
                
                return $this->fail([
                    'status' => $statusCode,
                    'orthanc_error_message' => json_decode($responseBody, true),
                    'plain_text' => $responseBody
                ], $statusCode);
            }

            return $this->respond([
                'status'          => 'success',
                'message'         => "Perintah kirim ke {$this->targetModality} berhasil dikirim",
                'patientId'       => $patientId,
                'patientUuid'     => $patientUuid,
                'targetModality'  => $this->targetModality
            ]);

        } catch (\Throwable $e) {
            log_message('error', $e->getMessage());
            return $this->failServerError('Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }
}