<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class DicomUpdateByPatientControllerNew extends ResourceController
{
    private $orthancUrl = 'http://192.168.105.34:8042/';
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

        // ========== 1. VALIDASI WAJIB ==========
        if (empty($input['oldPatientId'])) {
            return $this->fail('oldPatientId wajib diisi (untuk mencari data pasien)');
        }

        $oldPatientId = trim($input['oldPatientId']);

        if (empty($oldPatientId)) {
            return $this->fail('oldPatientId tidak boleh kosong/hanya spasi');
        }

        // ========== 2. KUMPULKAN PERUBAHAN PATIENT ==========
        $patientReplace = [];
        $patientChanges = [];

        // --- PatientID ---
        if (array_key_exists('newPatientId', $input)) {
            $newPatientId = trim($input['newPatientId']);
            if ($oldPatientId !== $newPatientId) {
                $patientReplace['PatientID'] = $newPatientId;
                $patientChanges['PatientID'] = [
                    'old' => $oldPatientId,
                    'new' => $newPatientId
                ];
            }
        }

        // --- PatientName ---
        if (array_key_exists('newPatientName', $input)) {
            $newPatientName = trim($input['newPatientName']);
            $hasOld = array_key_exists('oldPatientName', $input);

            if ($hasOld) {
                $oldPatientName = trim($input['oldPatientName']);
                if ($oldPatientName !== $newPatientName) {
                    $patientReplace['PatientName'] = $newPatientName;
                    $patientChanges['PatientName'] = [
                        'old' => $oldPatientName ?: '(KOSONG)',
                        'new' => $newPatientName ?: '(KOSONG)'
                    ];
                }
            } else {
                $patientReplace['PatientName'] = $newPatientName;
                $patientChanges['PatientName'] = [
                    'old' => '(force update)',
                    'new' => $newPatientName
                ];
            }
        }

        // --- PatientBirthDate ---
        if (array_key_exists('newPatientBirthDate', $input)) {
            $newPatientBirthDate = trim($input['newPatientBirthDate']);
            $hasOld = array_key_exists('oldPatientBirthDate', $input);

            if ($hasOld) {
                $oldPatientBirthDate = trim($input['oldPatientBirthDate']);
                if ($oldPatientBirthDate !== $newPatientBirthDate) {
                    $patientReplace['PatientBirthDate'] = $newPatientBirthDate;
                    $patientChanges['PatientBirthDate'] = [
                        'old' => $oldPatientBirthDate ?: '(KOSONG)',
                        'new' => $newPatientBirthDate ?: '(KOSONG)'
                    ];
                }
            } else {
                $patientReplace['PatientBirthDate'] = $newPatientBirthDate;
                $patientChanges['PatientBirthDate'] = [
                    'old' => '(force update)',
                    'new' => $newPatientBirthDate
                ];
            }
        }

        // ========== 3. CEK APAKAH ADA PERUBAHAN ==========
        $hasAccessionUpdate = array_key_exists('newAccessionNumber', $input);

        if (empty($patientReplace) && !$hasAccessionUpdate) {
            return $this->respond([
                'status'  => 'no_changes',
                'message' => 'Tidak ada perubahan, proses dibatalkan'
            ]);
        }

        $client = \Config\Services::curlrequest();

        try {
            // ========== 4. CARI UUID PASIEN ==========
            $listResponse = $client->get($this->orthancUrl . 'patients', array_merge($this->auth, ['http_errors' => false]));
            $patients = json_decode($listResponse->getBody(), true);

            if ($listResponse->getStatusCode() != 200) {
                return $this->failServerError("Gagal koneksi ke Orthanc: " . $listResponse->getBody());
            }

            $patientUuid = null;
            $patientStudies = [];

            foreach ($patients as $uuid) {
                $detailResponse = $client->get($this->orthancUrl . "patients/$uuid?expand", array_merge($this->auth, ['http_errors' => false]));
                $detail = json_decode($detailResponse->getBody(), true);

                if (($detail['MainDicomTags']['PatientID'] ?? '') === $oldPatientId) {
                    $patientUuid = $uuid;
                    $studiesRaw = $detail['Studies'] ?? [];
                    foreach ($studiesRaw as $s) {
                        if (is_string($s)) {
                            $patientStudies[] = $s;
                        } elseif (is_array($s) && isset($s['ID'])) {
                            $patientStudies[] = $s['ID'];
                        }
                    }
                    break;
                }
            }

            if (!$patientUuid) {
                return $this->failNotFound("PatientID '$oldPatientId' tidak ditemukan");
            }

            $results = [];

            // ========== 5. UPDATE ACCESSION NUMBER ==========
            if ($hasAccessionUpdate) {
                $newAccessionNumber = trim($input['newAccessionNumber']);

                if (empty($patientStudies)) {
                    return $this->failNotFound("Pasien ini tidak memiliki study");
                }

                $updatedStudies = [];
                $skippedStudies = [];

                foreach ($patientStudies as $studyUuid) {
                    $studyDetailResponse = $client->get(
                        $this->orthancUrl . "studies/$studyUuid",
                        array_merge($this->auth, ['http_errors' => false])
                    );
                    $studyDetail = json_decode($studyDetailResponse->getBody(), true);
                    $currentAccNum = $studyDetail['MainDicomTags']['AccessionNumber'] ?? '';

                    // Skip jika sudah sama
                    if ($currentAccNum === $newAccessionNumber) {
                        $skippedStudies[] = [
                            'studyUuid'     => $studyUuid,
                            'currentAccNum' => $currentAccNum ?: '(KOSONG)',
                            'reason'        => 'Sudah sama, tidak perlu diupdate'
                        ];
                        continue;
                    }

                    $oldAccNum = $currentAccNum ?: '(KOSONG)';

                    $modifyUrl = $this->orthancUrl . "studies/$studyUuid/modify";
                    $payload = [
                        'json' => [
                            'Force'      => true,
                            'KeepSource' => false,  // ← TAMBAHKAN INI
                            'Replace'    => [
                                'AccessionNumber' => $newAccessionNumber
                            ]
                        ],
                        'http_errors' => false
                    ];

                    $modifyResponse = $client->post($modifyUrl, array_merge($this->auth, $payload));

                    if ($modifyResponse->getStatusCode() !== 200) {
                        return $this->failServerError("Gagal update AccessionNumber: " . $modifyResponse->getBody());
                    }

                    $studyResult = json_decode($modifyResponse->getBody(), true);
                    $newStudyUuid = $studyResult['ID'] ?? null;

                    // Tidak perlu delete manual, KeepSource: false sudah handle
                    $updatedStudies[] = [
                        'oldStudyUuid' => $studyUuid,
                        'newStudyUuid' => $newStudyUuid,
                        'oldAccNum'    => $oldAccNum,
                        'newAccNum'    => $newAccessionNumber ?: '(KOSONG)'
                    ];
                }

                if (empty($updatedStudies) && empty($patientReplace)) {
                    return $this->respond([
                        'status'  => 'no_changes',
                        'message' => 'Tidak ada perubahan, semua data sudah sesuai',
                        'accession_detail' => [
                            'total_skipped' => count($skippedStudies),
                            'skipped'       => $skippedStudies
                        ]
                    ]);
                }

                $results['accession'] = [
                    'status'        => 'success',
                    'total_updated' => count($updatedStudies),
                    'total_skipped' => count($skippedStudies),
                    'newAccNum'     => $newAccessionNumber ?: '(KOSONG)',
                    'updated'       => $updatedStudies,
                    'skipped'       => $skippedStudies
                ];
            }

            // ========== 6. UPDATE PATIENT ==========
            if (!empty($patientReplace)) {
                $modifyUrl = $this->orthancUrl . "patients/$patientUuid/modify";
                $payload = [
                    'json' => [
                        'Force'      => true,
                        'KeepSource' => false,  // ← TAMBAHKAN INI
                        'Replace'    => $patientReplace
                    ],
                    'http_errors' => false
                ];

                $modifyResponse = $client->post($modifyUrl, array_merge($this->auth, $payload));

                if ($modifyResponse->getStatusCode() !== 200) {
                    return $this->failServerError("Gagal update Patient: " . $modifyResponse->getBody());
                }

                $patientResult = json_decode($modifyResponse->getBody(), true);
                $newPatientUuid = $patientResult['ID'] ?? null;

                // Tidak perlu delete manual, KeepSource: false sudah handle
                $results['patient'] = [
                    'status'         => 'success',
                    'changed_fields' => $patientChanges,
                    'oldPatientUuid' => $patientUuid,
                    'newPatientUuid' => $newPatientUuid
                ];
            }

            return $this->respond([
                'status'  => 'success',
                'message' => 'Update berhasil dilakukan',
                'results' => $results
            ]);

        } catch (\Throwable $e) {
            log_message('error', $e->getMessage());
            return $this->failServerError('Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }
}