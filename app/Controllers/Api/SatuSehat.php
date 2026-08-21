<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\Register;
use App\Models\SatuSehatLogModel;
use App\Models\ApotekModel;
use App\Models\LaboratoriumModel;
use App\Services\SatusehatService;
use App\Controllers\Api\SatuSehat\Condition\EncounterDiagnosis;
use App\Controllers\Api\SatuSehat\Condition\Anamesis\KeluhanUtama;
use App\Controllers\Api\SatuSehat\Condition\MeninggalkanFaskes;
use App\Controllers\Api\SatuSehat\Procedure\Procedure;
// use App\Controllers\Api\SatuSehat\Condition\Anamesis\RiwayatPenyakitSekarang;
// use App\Controllers\Api\SatuSehat\Condition\Anamesis\RiwayatPenyakitTerdahulu;
use App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanTandaVital\TDSistolik;
use App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanTandaVital\TDDiastolik;
use App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanTandaVital\SuhuTubuh;
use App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanTandaVital\DenyutJantung;
use App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanTandaVital\FrekuensiPernapasan;
use App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanTandaVital\SaturasiOksigen;
use App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\TingkatKesadaran\TingkatKesadaran;
use App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanFisikHeadToToe\Kepala;
use App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanFisikHeadToToe\Mata;
use App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanFisikHeadToToe\Telinga;
use App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanAntropometri\TinggiBadan;
use App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanAntropometri\BeratBadan;
use App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanAntropometri\LuasPermukaanTubuh;
use App\Controllers\Api\SatuSehat\ClinicalImpression\ClinicalImpression;
use App\Controllers\Api\SatuSehat\ClinicalImpression\RiwayatPerjalananPenyakit\RiwayatPerjalananPenyakit;
use App\Controllers\Api\SatuSehat\Goal\TujuanPerawatan\TujuanPerawatan;
use App\Controllers\Api\SatuSehat\CarePlan\CarePlan;
use App\Controllers\Api\SatuSehat\CarePlan\RencanaRawatJalanPasien\RencanaRawatJalanPasien;
use App\Controllers\Api\SatuSehat\Medication\Medication;
use App\Controllers\Api\SatuSehat\MedicationRequest\MedicationRequest;
use App\Controllers\Api\SatuSehat\Composition\Composition;
use App\Controllers\Api\SatuSehat\MedicationDispense\MedicationDispense;
use App\Controllers\Api\SatuSehat\CarePlan\InstruksiMedikdanKeperawatanPasien\InstruksiMedikdanKeperawatanPasien;
use App\Controllers\Api\SatuSehat\Composition\EdukasiDiet;
use App\Controllers\Api\SatuSehat\EpisodeOfCare\EpisodeOfCare;
use App\Controllers\Api\SatuSehat\AllergyIntolerance\AllergyIntolerance;
use App\Controllers\Api\SatuSehat\ServiceRequest\Laboratorium\LaboratoriumServiceRequest;
use App\Controllers\Api\SatuSehat\Specimen\Laboratorium\LaboratoriumSpecimen;
use App\Controllers\Api\SatuSehat\Observation\Laboratorium\LaboratoriumObservation;
use App\Controllers\Api\SatuSehat\DiagnosticReport\Laboratorium\LaboratoriumDiagnosticReport;
use App\Controllers\Api\SatuSehat\Procedure\Laboratorium\PanelNominal\StatusPuasa;
use App\Controllers\Api\SatuSehat\DiagnosticReport\DiagnosticReport;
use App\Controllers\Api\SatuSehat\Immunization\ImmunizationPush;
use App\Controllers\Api\SatuSehat\QuestionnaireResponse\QuestionnaireResponse;
use App\Controllers\Api\SatuSehat\MedicationStatement\MedicationStatement;

class SatuSehat extends BaseController
{
    protected $service;

    public function __construct()
    {
        set_time_limit(0);
        ini_set('max_execution_time', 0);
        $this->service = new SatusehatService();
    }


    public function hitEncounterWithIhs()
    {
        $regno = $this->request->getPost('regno');

        if (empty($regno)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Regno is required'])->setStatusCode(400);
        }

        $registerModel = new Register();
        $row = $registerModel->getDataByRegno($regno);

        if (!$row) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Data not found for regno: ' . $regno])->setStatusCode(404);
        }
        if($row['KdDocSatuSehat'] == '')
        {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Data Kode Dokter Satu Sehat tidak ditemukan'])->setStatusCode(404);
        }

        // 1. Resolve IHS
        $ihsRes = $this->resolveIhsId($row);
        if ($ihsRes !== true) {
            return $this->response->setJSON(['status' => 'error', 'message' => $ihsRes])->setStatusCode(500);
        }

        // 2. Check for existing Encounter
        $encounterId = $row['EcounterSatuSehat'] ?? null;
        if (!$encounterId) {
            // Search on Kemkes
            try {
                $encRes = $this->service->get('Encounter', [
                    'subject' => $row['IHSSatuSehat'],
                    'identifier' => 'http://sys-ids.kemkes.go.id/encounter/' . $this->getOrgId() . '|' . $regno
                ]);

                if (isset($encRes['entry']) && count($encRes['entry']) > 0) {
                    $encounterId = $encRes['entry'][0]['resource']['id'];
                }
            } catch (\Exception $e) {
                // Ignore search error
            }
        }

        // 3. Create Encounter if still missing
        if (!$encounterId) {
            try {
                $payload = $this->buildEncounterPayload($row);
                $res = $this->service->post('Encounter', $payload);
                if (isset($res['id'])) {
                    $encounterId = $res['id'];
                    // Save to local DB
                    $registerModel->updateEncounter($regno, $row['Medrec'], $encounterId);
                } else {
                    return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to create Encounter', 'detail' => $res])->setStatusCode(500);
                }
            } catch (\Exception $e) {
                return $this->response->setJSON(['status' => 'error', 'message' => 'Encounter creation error: ' . $e->getMessage()])->setStatusCode(500);
            }
        }

        return $this->response->setJSON([
            'status' => 'success',
            'ihs_id' => $row['IHSSatuSehat'],
            'encounter_id' => $encounterId
        ]);
    }

    /**
     * Fetch IHS ID dari SatuSehat berdasarkan NIK, simpan ke DB & update $row.
     * Return true jika berhasil, false/error string jika gagal.
     */
    private function resolveIhsId(&$row): bool|string
    {
        if (!empty($row['IHSSatuSehat'])) {
            return true; // sudah ada
        }

        $nik = $row['NoIden'] ?? null;
        if (empty($nik)) {
            return 'IHS dan NIK kosong, tidak bisa resolve pasien.';
        }
        if (empty($row['KdDocSatuSehat'])) {
            return 'KdDocSatuSehat missing. (Dokter belum di-mapping ke SatuSehat)';
        }
        if (empty($row['IdRuanganKemenkes'])) {
            return 'IdRuanganKemenkes missing. (Ruangan/Poli belum di-mapping ke SatuSehat)';
        }

        try {
            $response = $this->service->get('Patient', [
                'identifier' => 'https://fhir.kemkes.go.id/id/nik|' . $nik
            ]);

            $ihsId = $response['entry'][0]['resource']['id'] ?? null;
            if (!$ihsId || ($response['total'] ?? 0) < 1) {
                return 'IHS ID tidak ditemukan untuk NIK: ' . $nik;
            }

            (new \App\Models\MasterPS())->update($row['Medrec'], ['IHSSatuSehat' => $ihsId]);
            $row['IHSSatuSehat'] = $ihsId;
            return true;
        } catch (\Exception $e) {
            return 'Gagal fetch IHS: ' . $e->getMessage();
        }
    }

    /**
     * Build Encounter payload (tanpa kirim ke API).
     */
    private function buildEncounterPayload(array $row): array
    {
        $orgId = $this->getOrgId();
        $dateOnly = date('Y-m-d', strtotime($row['Regdate']));
        $timeOnly = date('H:i:s', strtotime($row['RegTime']));
        $startDateTime = date('c', strtotime($dateOnly . ' ' . $timeOnly));

        $serviceTypeMap = [
            '01' => ['code' => '408464004', 'display' => 'Ophthalmology service'],
            '03' => ['code' => '419192003', 'display' => 'Internal medicine'],
            '04' => ['code' => '394609007', 'display' => 'Surgical service'],
            '05' => ['code' => '408472002', 'display' => 'Obstetrics and gynecology service'],
            '07' => ['code' => '408478003', 'display' => 'Pediatric service'],
            '08' => ['code' => '394579002', 'display' => 'Cardiology service'],
            '09' => ['code' => '394591006', 'display' => 'Neurology'],
            '10' => ['code' => '408470005', 'display' => 'Otolaryngology service'],
            '11' => ['code' => '394610002', 'display' => 'Urology'],
            '12' => ['code' => '408467006', 'display' => 'Dermatology service'],
            '13' => ['code' => '408468001', 'display' => 'Neurosurgical service'],
            '14' => ['code' => '408471009', 'display' => 'Orthopedic service'],
            '15' => ['code' => '408460008', 'display' => 'Psychiatry service'],
            '16' => ['code' => '408444009', 'display' => 'Dental service'],
            '17' => ['code' => '408444009', 'display' => 'Dental service'],
            '18' => ['code' => '419192003', 'display' => 'Internal medicine'],
            '20' => ['code' => '408466002', 'display' => 'Anesthesiology service'],
            '21' => ['code' => '419192003', 'display' => 'Internal medicine'],
            '30' => ['code' => '419192003', 'display' => 'Internal medicine'],
            '31' => ['code' => '419192003', 'display' => 'Internal medicine'],
            '32' => ['code' => '394609007', 'display' => 'Surgical service'],
            '33' => ['code' => '408465003', 'display' => 'Oral and maxillofacial surgery service'],
            '35' => ['code' => '419192003', 'display' => 'Internal medicine'],
            '36' => ['code' => '394579002', 'display' => 'Cardiology service'],
            '37' => ['code' => '408464004', 'display' => 'Ophthalmology service'],
            '38' => ['code' => '394591006', 'display' => 'Neurology'],
            '39' => ['code' => '408472002', 'display' => 'Obstetrics and gynecology service'],
            '40' => ['code' => '408478003', 'display' => 'Pediatric service'],
            '41' => ['code' => '394609007', 'display' => 'Surgical service'],
            '42' => ['code' => '408443003', 'display' => 'General medical service'],
            '43' => ['code' => '408443003', 'display' => 'General medical service'],
            '44' => ['code' => '722174002', 'display' => 'Pulmonary medicine service'],
            '46' => ['code' => '408472002', 'display' => 'Maternal and child health service'],
            '47' => ['code' => '394602003', 'display' => 'Rehabilitation medicine'],
            '48' => ['code' => '419192003', 'display' => 'Internal medicine'],
            '49' => ['code' => '419192003', 'display' => 'Internal medicine service'],
            '50' => ['code' => '410158009', 'display' => 'Physiotherapy service'],
            '52' => ['code' => '702873001', 'display' => 'Health check service'],
            '53' => ['code' => '408460008', 'display' => 'Psychiatry service'],
            '54' => ['code' => '185389009', 'display' => 'Home visit service'],
            '55' => ['code' => '419192003', 'display' => 'Internal medicine'],
            '56' => ['code' => '419192003', 'display' => 'Internal medicine'],
            '57' => ['code' => '308335008', 'display' => 'Hospital admission service'],
            '58' => ['code' => '419192003', 'display' => 'Internal medicine'],
        ];

        $kdPoli = $row['KdPoli'] ?? '';
        $stCode = $serviceTypeMap[$kdPoli]['code'] ?? '419192003';
        $stDisplay = $serviceTypeMap[$kdPoli]['display'] ?? 'Internal medicine';

        if (in_array($stCode, ['408457005', '408478007', '408469000', '408476003', '408474000', '408455002', '408450007', '722163007', '722164001', '408462001', '722162002'])) {
            $stCode = '419192003';
            $stDisplay = 'Internal medicine';
        }

        $isEmer = ($kdPoli === '30');
        $isRanap = (strtoupper($row['KdTuju'] ?? '') === 'RI' || strtoupper($row['KdTuju'] ?? '') === '1');

        $classCode = 'AMB';
        $classDisplay = 'ambulatory';
        if ($isEmer) {
            $classCode = 'EMER';
            $classDisplay = 'emergency';
        } elseif ($isRanap) {
            $classCode = 'IMP';
            $classDisplay = 'inpatient';
        }

        $locationServiceSystem = $isRanap 
            ? 'http://terminology.kemkes.go.id/CodeSystem/locationServiceClass-Inpatient' 
            : 'http://terminology.kemkes.go.id/CodeSystem/locationServiceClass-Outpatient';

        $locationServiceCode = !empty($row['LocationServiceClassCode']) 
            ? $row['LocationServiceClassCode'] 
            : ($isRanap ? '3' : 'reguler');

        $locationServiceDisplay = !empty($row['LocationServiceClassDisplay']) 
            ? $row['LocationServiceClassDisplay'] 
            : ($isRanap ? 'Kelas 3' : 'Kelas Reguler');

        $status = 'arrived';
        $statusHistory = [
            [
                'status' => 'arrived',
                'period' => ['start' => $startDateTime],
            ]
        ];
        $period = ['start' => $startDateTime];

        // Skip dulu untuk pasien pulang (di-comment sementara)
        /*
        if (!empty($row['TglPulang'])) {
            $status = 'finished';
            $pDate = date('Y-m-d', strtotime($row['TglPulang']));
            $pTime = !empty($row['JamPulang']) ? date('H:i:s', strtotime($row['JamPulang'])) : '23:59:59';
            $endDateTime = date('c', strtotime($pDate . ' ' . $pTime));

            $statusHistory[0]['period']['end'] = $endDateTime;
            $statusHistory[] = [
                'status' => 'finished',
                'period' => [
                    'start' => $endDateTime,
                    'end' => $endDateTime
                ]
            ];
            $period['end'] = $endDateTime;
        }
        */

        $locations = [
            [
                'location' => [
                    'reference' => 'Location/' . $row['IdRuanganKemenkes'],
                    'display' => $row['NmRuanganKemenkes'],
                ],
                'period' => ['start' => $startDateTime],
                'extension' => [
                    [
                        'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/ServiceClass',
                        'extension' => [
                            [
                                'url' => 'value',
                                'valueCodeableConcept' => [
                                    'coding' => [
                                        [
                                            'system' => $locationServiceSystem,
                                            'code' => $locationServiceCode,
                                            'display' => $locationServiceDisplay,
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'url' => 'upgradeClassIndicator',
                                'valueCodeableConcept' => [
                                    'coding' => [
                                        [
                                            'system' => 'http://terminology.kemkes.go.id/CodeSystem/locationUpgradeClass',
                                            'code' => (!empty($row['LocationUpgradeClassCode']) ? $row['LocationUpgradeClassCode'] : 'kelas-tetap'),
                                            'display' => (!empty($row['LocationUpgradeClassDisplay']) ? $row['LocationUpgradeClassDisplay'] : 'Kelas Tetap Perawatan'),
                                        ]
                                    ]
                                ]
                            ],
                        ]
                    ]
                ]
            ]
        ];

        if (!empty($row['IdBedKemenkes'])) {
            $locations[] = [
                'location' => [
                    'reference' => 'Location/' . $row['IdBedKemenkes'],
                    'display' => $row['NmBedKemenkes'],
                ],
                'period' => ['start' => $startDateTime],
            ];
        }

        return [
            'resourceType' => 'Encounter',
            'identifier' => [
                [
                    'system' => 'http://sys-ids.kemkes.go.id/encounter/' . $orgId,
                    'value' => $row['Regno'],
                ]
            ],
            'status' => $status,
            'statusHistory' => $statusHistory,
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => $classCode,
                'display' => $classDisplay,
              ],
            'serviceType' => [
                'coding' => [
                    [
                        'system' => 'http://snomed.info/sct',
                        'code' => $stCode,
                        'display' => $stDisplay,
                    ]
                ]
            ],
            'subject' => [
                'reference' => 'Patient/' . $row['IHSSatuSehat'],
                'display' => $row['Firstname'],
            ],
            'participant' => [
                [
                    'type' => [
                        [
                            'coding' => [
                                [
                                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-ParticipationType',
                                    'code' => 'ATND',
                                    'display' => 'attender',
                                ]
                            ]
                        ]
                    ],
                    'individual' => [
                        'reference' => 'Practitioner/' . $row['KdDocSatuSehat'],
                        'display' => $row['NmDoc'],
                    ],
                ]
            ],
            'period' => $period,
            'location' => $locations,
            'serviceProvider' => [
                'reference' => 'Organization/' . $orgId,
            ],
        ];
    }

    /**
     * Post-process array payload secara rekursif:
     * Replace semua reference "Encounter/{uuid}" → "urn:uuid:{uuid}"
     * agar FHIR Bundle transaction dapat resolve internal reference.
     */
    private function fixEncounterRef(array $payload, string $encounterUuid): array
    {
        $search = 'Encounter/' . $encounterUuid;
        $replace = 'urn:uuid:' . $encounterUuid;

        array_walk_recursive($payload, function (&$val) use ($search, $replace) {
            if (is_string($val) && $val === $search) {
                $val = $replace;
            }
        });

        return $payload;
    }


    // Helper to generate UUID v4
    private function generateUuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    private function processRegnoBundle($row, $skippedKfas = [], int $retryCount = 0)
    {
        if ($retryCount >= 3) {
            log_message('error', "processRegnoBundle reached max retries (3) for Regno " . ($row['Regno'] ?? 'UNKNOWN'));
            return [
                'regno'   => $row['Regno'] ?? 'UNKNOWN',
                'status'  => 'error',
                'message' => 'Max retries (3) reached during bundle recovery.',
            ];
        }

        // Sanitize missing Practitioner IDs
        if (empty(trim($row['KdDocSatuSehat'] ?? '')))
            $row['KdDocSatuSehat'] = '';
        if (empty(trim($row['kdDocSatuSehatRad'] ?? '')))
            $row['kdDocSatuSehatRad'] = '';
        if (empty(trim($row['KdDocSatuSehatLab'] ?? '')))
            $row['KdDocSatuSehatLab'] = '';
        if (empty(trim($row['PerformerId'] ?? '')))
            $row['PerformerId'] = '';
        if (empty(trim($row['PerformerRadiologi'] ?? '')))
            $row['PerformerRadiologi'] = '';
        if (empty(trim($row['Practitioner_id'] ?? '')))
            $row['Practitioner_id'] = '';

        // ── 1. Resolve IHS Patient ID (GET saja, tidak kirim bundle) ─────────
        $ihs = $this->resolveIhsId($row);
        if ($ihs !== true) {
            return ['regno' => $row['Regno'], 'status' => 'failed', 'message' => $ihs];
        }

        // ── 2. Cek apakah Encounter sudah terkirim (di Local DB atau Kemkes API) ───────
        $alreadySentEncounterId = !empty($row['EcounterSatuSehat']) ? $row['EcounterSatuSehat'] : null;

        if (!$alreadySentEncounterId || $alreadySentEncounterId === 'PENDING-SYNC') {
            try {
                $orgId = $this->getOrgId();
                $encIdentifier = 'http://sys-ids.kemkes.go.id/encounter/' . $orgId . '|' . $row['Regno'];
                
                // Cari Encounter di Kemkes menggunakan helper pencarian ganda (subject & identifier)
                $matchedEncounters = $this->findMatchingResourceOnKemkes('Encounter', $encIdentifier, $row);

                $foundId = null;
                if (!empty($matchedEncounters)) {
                    $foundId = $matchedEncounters[0]['resource']['id'] ?? null;

                    // Hapus duplikat Encounter ekstra jika ada >1 di Kemkes
                    if (count($matchedEncounters) > 1) {
                        for ($i = 1; $i < count($matchedEncounters); $i++) {
                            $dupId = $matchedEncounters[$i]['resource']['id'] ?? null;
                            if ($dupId) {
                                try { $this->service->delete('Encounter', $dupId); } catch (\Exception $delEx) {}
                            }
                        }
                    }
                }

                if (!empty($foundId)) {
                    $alreadySentEncounterId = $foundId;
                    // Simpan ID yang sudah ketemu ke database lokal
                    $registerModel = new \App\Models\Register();
                    $registerModel->updateEncounter($row['Regno'], $row['Medrec'], $alreadySentEncounterId);
                } else if ($alreadySentEncounterId === 'PENDING-SYNC') {
                    // Api Kemkes masih mengindeks data yang sudah disubmit secara parsial
                    return [
                        'regno' => $row['Regno'],
                        'status' => 'skipped',
                        'id' => 'PENDING-SYNC',
                        'message' => 'Sedang di-index oleh Kemkes (Bypass Error 20002).'
                    ];
                }
            } catch (\Exception $e) {
                log_message('error', 'Check Encounter error for Regno ' . $row['Regno'] . ': ' . $e->getMessage());
            }
        }


        // UUID ini dipakai di seluruh bundle sebagai "identity" Encounter di-request
        $encounterUuid = $this->generateUuid();
        $encounterFullUrl = 'urn:uuid:' . $encounterUuid;

        // $encounterId yang di-pass ke buildPayload.
        // buildPayload akan produce "Encounter/{encounterId}",
        // lalu fixEncounterRef() mengubahnya → "urn:uuid:{encounterUuid}".
        // Jika encounter sudah ada (skip), gunakan real ID-nya langsung.
        $encounterId = $alreadySentEncounterId ?? $encounterUuid;

        $entries = [];
        $entryKeys = [];

        // Ambil daftar resource subtype yang sudah pernah berhasil dikirim untuk Regno ini
        $sentResourcesModel = new SatuSehatLogModel();
        $sentRows = $sentResourcesModel->where('Regno', $row['Regno'])->findAll();
        $sentSubtypes = [];
        foreach ($sentRows as $sr) {
            $typeKey = $sr['resourceType'] . ':' . ($sr['resourceSubType'] ?? '');
            $sentSubtypes[$typeKey] = $sr['resourceID'];
        }

        // ── 3. Helper: tambah entry ke bundle ────────────────────────────────
        $addEntry = function ($payload, $key, $method = 'POST', $url = null, $fixRef = true, $customFullUrl = null) use (&$entries, &$entryKeys, $encounterUuid, $alreadySentEncounterId, $sentSubtypes) {
            if (!$payload)
                return;

            $type = $key['type'] ?? ($payload['resourceType'] ?? '');
            $subtype = $key['subtype'] ?? '';
            $sentKey = $type . ':' . $subtype;

            // Skip jika subtype resource ini sudah pernah berhasil dikirim & dicatat di DB untuk Regno ini
            if (!empty($subtype) && isset($sentSubtypes[$sentKey])) {
                return;
            }

            if (!$url)
                $url = $payload['resourceType'];

            // Post-process hanya jika Encounter belum di-send (UUID dipakai sebagai placeholder)
            if ($fixRef && !$alreadySentEncounterId) {
                $payload = $this->fixEncounterRef($payload, $encounterUuid);
            }

            $entries[] = [
                'fullUrl' => $customFullUrl ?: ('urn:uuid:' . $this->generateUuid()),
                'resource' => $payload,
                'request' => ['method' => $method, 'url' => $url],
            ];
            $entryKeys[] = $key;
        };

        // ── 4. Encounter masuk bundle sebagai ENTRY PERTAMA ───────────────────
        if ($alreadySentEncounterId) {
            // Encounter sudah ada → tidak masuk bundle, langsung reference real ID
        } else {
            // Encounter baru → masuk sebagai POST dengan fullUrl = encounterFullUrl
            $entries[] = [
                'fullUrl' => $encounterFullUrl,
                'resource' => $this->buildEncounterPayload($row),
                'request' => [
                    'method' => 'POST',
                    'url' => 'Encounter',
                    'ifNoneExist' => 'identifier=http://sys-ids.kemkes.go.id/encounter/' . $this->getOrgId() . '|' . $row['Regno']
                ],
            ];
            $entryKeys[] = ['type' => 'Encounter', 'subtype' => 'encounter'];
        }

        // 2. Conditions
        $diagnosis = new EncounterDiagnosis($this->service);
        $keluhanUtama = new KeluhanUtama($this->service);
        $meninggalkanFaskes = new MeninggalkanFaskes($this->service);

        if (method_exists($diagnosis, 'buildPayload'))
            $addEntry($diagnosis->buildPayload($row, $encounterId), ['type' => 'Condition', 'subtype' => 'diagnosis']);

        $keluhanUtamaPayload = null;
        $keluhanUtamaUuid = null;
        if (method_exists($keluhanUtama, 'buildPayload')) {
            $keluhanUtamaPayload = $keluhanUtama->buildPayload($row, $encounterId);
            if ($keluhanUtamaPayload) {
                $keluhanUtamaUuid = 'urn:uuid:' . $this->generateUuid();
                $addEntry($keluhanUtamaPayload, ['type' => 'Condition', 'subtype' => 'keluhan_utama'], 'POST', 'Condition', true, $keluhanUtamaUuid);
            }
        }

        if (method_exists($meninggalkanFaskes, 'buildPayload'))
            $addEntry($meninggalkanFaskes->buildPayload($row, $encounterId), ['type' => 'Condition', 'subtype' => 'meninggalkan_faskes']);

        // 3. Observations
        $obsClasses = [
            'td_sistolik' => new TDSistolik($this->service),
            'td_diastolik' => new TDDiastolik($this->service),
            'suhu_tubuh' => new SuhuTubuh($this->service),
            'denyut_jantung' => new DenyutJantung($this->service),
            'frekuensi_pernapasan' => new FrekuensiPernapasan($this->service),
            'saturasi_oksigen' => new SaturasiOksigen($this->service),
            'tinggi_badan' => new TinggiBadan($this->service),
            'berat_badan' => new BeratBadan($this->service),
        ];
        foreach ($obsClasses as $key => $obs) {
            if (method_exists($obs, 'buildPayload')) {
                $addEntry($obs->buildPayload($row, $encounterId), ['type' => 'Observation', 'subtype' => $key]);
            }
        }

        // 4. Clinical Impression
        // Needs Keluhan Utama ID. If we have UUID, pass it.
        // But buildPayload expects "Condition/ID".
        // If we pass "urn:uuid:...", we need to ensure buildPayload doesn't prepend "Condition/".
        // ClinicalImpression buildPayload likely prepends.
        // Assumption: Most controllers prepend "Condition/".
        // Fix: We might skip linking Keluhan Utama in Bundle for now OR we rely on post-processing? No, must be in bundle.
        // For this task, I will pass null for optional links if I can't safely use UUIDs without modifying all controllers.
        // EXCEPT: Meds and Labs are critical to bundle together.
        // ClinicalImpression link to Keluhan Utama is optional? 
        // Let's check ClinicalImpression.php.
        // It says: "condition" => ["reference" => "Condition/" . $keluhanUtamaId] (in general).
        // If I pass UUID, it breaks.
        // I will pass null for $keluhanUtamaId for now to avoid breaking references, unless I fix the controller.
        // Validated: I fixed EpisodeOfCare to use UUID? No, I fixed it to extract buildPayload.
        // Use $keluhanUtamaId = null for now.

        $clinicalImpression = new ClinicalImpression($this->service);
        $riwayatPerjalananPenyakit = new RiwayatPerjalananPenyakit($this->service);

        if (method_exists($clinicalImpression, 'buildPayload'))
            $addEntry($clinicalImpression->buildPayload($row, $encounterId, null), ['type' => 'ClinicalImpression', 'subtype' => 'clinical_impression']);
        if (method_exists($riwayatPerjalananPenyakit, 'buildPayload'))
            $addEntry($riwayatPerjalananPenyakit->buildPayload($row, $encounterId), ['type' => 'ClinicalImpression', 'subtype' => 'riwayat_perjalanan_penyakit']);

        // 5. Goal & CarePlan
        // Similar issue with Goal ID -> CarePlan.
        // I will bundle them but not link them internally if they depend on IDs, to be safe.
        // Or I can implement UUID logic.
        // Let's stick to bundling independent resources + Meds/Labs (which I will handle carefully).

        // 5. Goal & CarePlan
        $goalAlreadySent = false;
        $goalLogModel = new SatuSehatLogModel();
        if ($goalLogModel->where('Regno', $row['Regno'])->where('resourceType', 'Goal')->countAllResults() > 0) {
            $goalAlreadySent = true;
        }

        if (!$goalAlreadySent && !empty($row['IHSSatuSehat'])) {
            try {
                $resGoal = $this->service->get('Goal', [
                    'subject' => $row['IHSSatuSehat']
                ]);
                if (($resGoal['total'] ?? 0) > 0) {
                    $goalAlreadySent = true;
                }
            } catch (\Exception $e) {
                // Abaikan jika pencarian Goal gagal
            }
        }

        if (!$goalAlreadySent) {
            $tujuanPerawatan = new TujuanPerawatan($this->service);
            if (method_exists($tujuanPerawatan, 'buildPayload')) {
                $goalPayload = $tujuanPerawatan->buildPayload($row, $encounterId, null);
                if ($goalPayload) {
                    $addEntry($goalPayload, ['type' => 'Goal', 'subtype' => 'tujuan_perawatan']);
                }
            }
        }
        // Goal UUID?

        $carePlan = new CarePlan($this->service);
        $rencanaRawatJalanPasien = new RencanaRawatJalanPasien($this->service);
        $instruksiMedik = new InstruksiMedikDanKeperawatanPasien($this->service);

        if (method_exists($carePlan, 'buildPayload'))
            $addEntry($carePlan->buildPayload($row, $encounterId, null), ['type' => 'CarePlan', 'subtype' => 'care_plan']);
        if (method_exists($rencanaRawatJalanPasien, 'buildPayload'))
            $addEntry($rencanaRawatJalanPasien->buildPayload($row, $encounterId, null), ['type' => 'CarePlan', 'subtype' => 'rencana_rawat_jalan_pasien']);
        if (method_exists($instruksiMedik, 'buildPayload'))
            $addEntry($instruksiMedik->buildPayload($row, $encounterId, null), ['type' => 'CarePlan', 'subtype' => 'instruksi_medik']);

        // 6. Composition
        $edukasiDiet = new EdukasiDiet($this->service);
        if (method_exists($edukasiDiet, 'buildPayload'))
            $addEntry($edukasiDiet->buildPayload($row, $encounterId), ['type' => 'Composition', 'subtype' => 'edukasi_diet']);

        // 7. Procedure
        $procedureController = new Procedure($this->service);
        if (method_exists($procedureController, 'buildPayload'))
            $addEntry($procedureController->buildPayload($row, $encounterId), ['type' => 'Procedure', 'subtype' => 'procedure']);

        // 8. EpisodeOfCare
        if ($row['KdPoli'] == '45' || $row['KdPoli'] == '44' || $row['KdPoli'] == '31') {
            $hasActiveEoc = false;
            try {
                // Cek apakah ada EpisodeOfCare aktif (misal TB/Kehamilan) di server Kemkes
                $resEoc = $this->service->get('EpisodeOfCare', [
                    'patient' => $row['IHSSatuSehat'],
                    'status' => 'active'
                ]);
                if (($resEoc['total'] ?? 0) > 0) {
                    $hasActiveEoc = true;
                }
            } catch (\Exception $e) {
                // Abaikan jika error, anggap tidak ada
            }

            // Jika belum ada yang aktif, tambahkan POST (Create baru) ke dalam bundle
            if (!$hasActiveEoc) {
                $episodeOfCareController = new EpisodeOfCare($this->service);
                if (method_exists($episodeOfCareController, 'buildPayload')) {
                    $addEntry($episodeOfCareController->buildPayload($row, $encounterId, null), ['type' => 'EpisodeOfCare', 'subtype' => 'episode_of_care']);
                }
            }
        }

        // 9. AllergyIntolerance
        $allergyController = new AllergyIntolerance($this->service);
        if (method_exists($allergyController, 'buildPayload'))
            $addEntry($allergyController->buildPayload($row, $encounterId), ['type' => 'AllergyIntolerance', 'subtype' => 'allergy']);

        // 10. Medications (The Big One)
        $apotekModel = new ApotekModel();
        $obatsTemp = $apotekModel->getObatByRegno($row['Regno']);
        $obatsDispense = $apotekModel->getDispenseObatByRegno($row['Regno']);

        // Trim KFA, NoResep, and KodeObat to prevent spacing/matching errors
        foreach ($obatsTemp as &$item) {
            if (isset($item['KFA'])) {
                $item['KFA'] = trim($item['KFA']);
            }
            if (isset($item['NoResep'])) {
                $item['NoResep'] = trim($item['NoResep']);
            }
            if (isset($item['KodeObat'])) {
                $item['KodeObat'] = trim($item['KodeObat']);
            }
        }
        unset($item);
        foreach ($obatsDispense as &$item) {
            if (isset($item['KFA'])) {
                $item['KFA'] = trim($item['KFA']);
            }
            if (isset($item['NoResep'])) {
                $item['NoResep'] = trim($item['NoResep']);
            }
            if (isset($item['KodeObat'])) {
                $item['KodeObat'] = trim($item['KodeObat']);
            }
        }
        unset($item);

        // Filter out skipped KFAs (invalid/unmapped KFA codes detected on previous attempts)
        if (!empty($skippedKfas)) {
            $obatsTemp = array_values(array_filter($obatsTemp, function($item) use ($skippedKfas) {
                $kfa = trim($item['KFA'] ?? '');
                return !in_array($kfa, $skippedKfas);
            }));
            $obatsDispense = array_values(array_filter($obatsDispense, function($item) use ($skippedKfas) {
                $kfa = trim($item['KFA'] ?? '');
                return !in_array($kfa, $skippedKfas);
            }));
        }

        $obats = !empty($obatsTemp) ? $obatsTemp : $obatsDispense;

        // $medicationController = new Medication($this->service);
        $medicationRequestController = new MedicationRequest($this->service);
        $medicationDispenseController = new MedicationDispense($this->service);

        $medUuidsByKode = []; // Hashmap untuk deduplikasi resource Medication

        // 1. Register unique Medication resources from both temp and dispense arrays
        // $allObats = array_merge($obatsTemp, $obatsDispense);
        // foreach ($allObats as $index => $obatItem) {
        //     $kfaKey    = trim($obatItem['KFA'] ?? '');
        //     $localKode = trim($obatItem['KodeObat'] ?? '');

        //     // Skip jika KFA kosong atau tidak valid (harus numerik, bukan kode barang lokal)
        //     if (empty($kfaKey) || !is_numeric($kfaKey)) {
        //         continue;
        //     }

        //     $kodeDeduplikasi = !empty($localKode) ? $localKode : $kfaKey;

        //     if (isset($medUuidsByKode[$kodeDeduplikasi]) || ($localKode && isset($medUuidsByKode[$localKode]))) {
        //         continue;
        //     }

        //     $existingMedId = !empty($obatItem['Medication_id_satu_sehat'])
        //         ? $obatItem['Medication_id_satu_sehat']
        //         : $this->resolveMedicationProactively($kfaKey, $localKode);

        //     if (!empty($existingMedId)) {
        //         // Medication sudah ada di DB lokal atau Kemkes -> gunakan ID aslinya, tidak perlu POST di bundle
        //         $medUuidsByKode[$kodeDeduplikasi] = $existingMedId;
        //         if ($localKode) $medUuidsByKode[$localKode] = $existingMedId;
        //         if ($kfaKey) $medUuidsByKode[$kfaKey] = $existingMedId;
        //     } else {
        //         $medUuid = 'urn:uuid:' . $this->generateUuid();
        //         $medUuidsByKode[$kodeDeduplikasi] = $medUuid;
        //         if ($localKode) $medUuidsByKode[$localKode] = $medUuid;
        //         if ($kfaKey) $medUuidsByKode[$kfaKey] = $medUuid;

        //         $tempObat = $obatItem;
        //         $tempObat['KodeObat'] = $kodeDeduplikasi;

        //         if (method_exists($medicationController, 'buildPayload')) {
        //             $medPayload = $medicationController->buildPayload($tempObat, $encounterId);
        //             if ($medPayload) {
        //                 $orgId = $this->getOrgId();
        //                 // Identifier Medication menggunakan KodeObat lokal agar 100% unik & spesifik per fasyankes
        //                 $medIdentifierValue = !empty($localKode) ? $localKode : $kodeDeduplikasi;
        //                 $medPayload['identifier'][0]['value'] = $medIdentifierValue;
        //                 $entries[] = [
        //                     "fullUrl" => $medUuid,
        //                     "resource" => $medPayload,
        //                     "request" => [
        //                         "method" => "POST",
        //                         "url" => "Medication",
        //                         "ifNoneExist" => "identifier=http://sys-ids.kemkes.go.id/medication/" . $orgId . "|" . $medIdentifierValue
        //                     ]
        //                 ];
        //                 $entryKeys[] = ['type' => 'Medication', 'subtype' => 'medication', 'local_id' => $localKode];
        //             }
        //         }
        //     }
        // }

        // 2. Process MedicationRequest (Prescription drafts from Temp)
        $medRequestUuids = []; // Map (NoResep_KodeObat) or KodeObat -> MedicationRequest UUID
        foreach ($obatsTemp as $index => $obat) {
            $kfaKey = trim($obat['KFA'] ?? '');
            // if (empty($kfaKey) || !is_numeric($kfaKey)) {
            //     continue; // Skip item tanpa KFA valid
            // }
            if (empty($kfaKey)) {
                continue; // Skip item tanpa KFA valid
            }
            $obat['Urutan'] = $index + 1;
            $itemKode = trim($obat['KodeObat'] ?? '');
            $medUuid = $obat['Medication_id_satu_sehat'];

            if (empty($medUuid)) {
                continue;
            }

            $reqUuid = 'urn:uuid:' . $this->generateUuid();
          

            // Map the MedicationRequest UUID
            $noResep = trim($obat['NoResep'] ?? ($obat['BLCode'] ?? ''));
            $mapKey = $noResep . '_' . $itemKode;
            $medRequestUuids[$mapKey] = $reqUuid;
            if ($itemKode && !isset($medRequestUuids[$itemKode])) {
                $medRequestUuids[$itemKode] = $reqUuid;
            }

            $reqData = array_merge($row, $obat);
            $reqData['NoResep'] = $noResep;
            $reqData['MedicationId'] = $medUuid;
            $reqData['TglResep'] = $obat['TglResep'] ?? ($obat['RegDate'] ?? ($obat['Regdate'] ?? null));
            $reqData['AturanPakai'] = $obat['AturanPakai'] ?? null;
            $notes = [];
            if (!empty($obat['NoteCaraMinumObat'])) $notes[] = $obat['NoteCaraMinumObat'];
            if (!empty($obat['NoteSigna'])) $notes[] = $obat['NoteSigna'];
            $reqData['CatatanMinum'] = implode(', ', $notes);
            $reqData['InstruksiPasien'] = $obat['KeteranganPakai'] ?? null;
            $reqData['JumlahObat'] = $obat['Qty'] ?? null;
            $reqData['SatuanObat'] = $obat['Satuan'] ?? 'TAB';

            if (method_exists($medicationRequestController, 'buildPayload')) {
                $reqPayload = $medicationRequestController->buildPayload($reqData, $encounterId);
                if ($reqPayload) {
                    $mrSubtype = 'medreq_' . ($noResep ?: 'no') . '_' . $itemKode . '_' . ($index + 1);
                    $addEntry($reqPayload, ['type' => 'MedicationRequest', 'subtype' => $mrSubtype, 'local_id' => $itemKode], 'POST', 'MedicationRequest', true, $reqUuid);
                }
            }
        }

        // 3. Process MedicationDispense (Dispensed medicines from Real Apotek)
        foreach ($obatsDispense as $index => $obat) {
            $kfaKey = trim($obat['KFA'] ?? '');
            if (empty($kfaKey) || !is_numeric($kfaKey)) {
                continue; // Skip item tanpa KFA valid
            }
            $medUuid = $obat['Medication_id_satu_sehat'];

            if (empty($medUuid)) {
                continue;
            }

            $itemKode = trim($obat['KodeObat'] ?? '');
            $noResep = trim($obat['NoResep'] ?? ($obat['BLCode'] ?? ''));
            $dispenseUrutan = (string)($obat['Urutan'] ?? ($index + 1));

            // Find matching MedicationRequest UUID
            $mapKey = $noResep . '_' . $itemKode;
            $reqUuid = $medRequestUuids[$mapKey] ?? ($medRequestUuids[$itemKode] ?? null);

            if (!$reqUuid) {
                // Generate a new MedicationRequest on the fly so MedicationDispense has its mandatory reference
                $reqUuid = 'urn:uuid:' . $this->generateUuid();
                $medRequestUuids[$mapKey] = $reqUuid;

                $reqData = array_merge($row, $obat);
                $reqData['Urutan'] = $dispenseUrutan;
                $reqData['MedicationId'] = $medUuid;
                $reqData['TglResep'] = $obat['TglResep'] ?? ($obat['RegDate'] ?? ($obat['Regdate'] ?? null));
                $reqData['AturanPakai'] = $obat['AturanPakai'] ?? null;
                $notes = [];
                if (!empty($obat['NoteCaraMinumObat'])) $notes[] = $obat['NoteCaraMinumObat'];
                if (!empty($obat['NoteSigna'])) $notes[] = $obat['NoteSigna'];
                $reqData['CatatanMinum'] = implode(', ', $notes);
                $reqData['InstruksiPasien'] = $obat['KeteranganPakai'] ?? null;
                $reqData['JumlahObat'] = $obat['Qty'] ?? null;
                $reqData['SatuanObat'] = $obat['Satuan'] ?? 'TAB';

                if (method_exists($medicationRequestController, 'buildPayload')) {
                    $reqPayload = $medicationRequestController->buildPayload($reqData, $encounterId);
                    if ($reqPayload) {
                        $mrSubtype = 'medreq_' . ($noResep ?: 'no') . '_' . $itemKode . '_' . $dispenseUrutan;
                        $addEntry($reqPayload, ['type' => 'MedicationRequest', 'subtype' => $mrSubtype, 'local_id' => $itemKode], 'POST', 'MedicationRequest', true, $reqUuid);
                    }
                }
            }

            $dispenseData = array_merge($row, $obat);
            $dispenseData['Urutan'] = $dispenseUrutan;
            $dispenseData['NoResep'] = $noResep;
            $dispenseData['MedicationId'] = $medUuid;

            if (method_exists($medicationDispenseController, 'buildPayload')) {
                $dispensePayload = $medicationDispenseController->buildPayload($dispenseData, $encounterId, $reqUuid);
                if ($dispensePayload) {
                    $mdSubtype = 'meddisp_' . ($noResep ?: 'no') . '_' . $itemKode . '_' . $dispenseUrutan;
                    $addEntry($dispensePayload, ['type' => 'MedicationDispense', 'subtype' => $mdSubtype, 'local_id' => $itemKode]);
                }
            }
        }

        // 11. Labs (The Other Big One)
        $labModel = new LaboratoriumModel();
        $labOrders = $labModel->getLabOrders($row['Regno']);
        $srController = new LaboratoriumServiceRequest($this->service);

        $srUuids = []; // Map NoTran -> UUID

        // Process Orders (ServiceRequest)
        foreach ($labOrders as $order) {
            $orderData = array_merge($row, $order);
            $srUuid = 'urn:uuid:' . $this->generateUuid();
            $srUuids[$order['NoTran']] = $srUuid;

            if (method_exists($srController, 'buildPayload')) {
                $srPayload = $srController->buildPayload($orderData, $encounterId);
                if ($srPayload) {
                    $entries[] = [
                        "fullUrl" => $srUuid,
                        "resource" => $srPayload,
                        "request" => ["method" => "POST", "url" => "ServiceRequest"]
                    ];
                    $entryKeys[] = ['type' => 'ServiceRequest', 'subtype' => 'laboratorium', 'local_id' => $order['NoTran']];
                }
            }
        }

        // Process Results
        $labResults = $labModel->getLabResults($row['Regno']);
        $groupedResults = [];
        foreach ($labResults as $res) {
            $groupedResults[$res['HeaderNoTran']][] = $res;
        }

        $statusPuasaController = new StatusPuasa($this->service);
        $specimenController = new LaboratoriumSpecimen($this->service);
        $obsLabController = new LaboratoriumObservation($this->service);
        $drController = new LaboratoriumDiagnosticReport($this->service); // Note: Original used DiagnosticReport/DiagnosticReport for lab results loop? No, processRowLab uses DiagnosticReport controller later. 
        // Wait, processRowLab calls $drController->push($labItem['dr_row']...) at the END of loop (lines 1427).
        // It uses `App\Controllers\Api\SatuSehat\DiagnosticReport\DiagnosticReport`.
        // But inside processRowLab logic (line 1636), it prepares $drRow.
        // Let's use `DiagnosticReport` controller as per processRowLab.

        $drController = new DiagnosticReport($this->service);

        foreach ($groupedResults as $noTran => $details) {
            if (empty($details))
                continue;

            $headerData = array_merge($row, $details[0]);

            // ServiceRequest UUID
            $srUuid = $srUuids[$noTran] ?? null;
            if (!$srUuid) {
                // Fallback: Create SR if missing (Logic from processRowLab lines 1604)
                $srUuid = 'urn:uuid:' . $this->generateUuid();
                if (method_exists($srController, 'buildPayload')) {
                    $srPayload = $srController->buildPayload($headerData, $encounterId);
                    if ($srPayload) {
                        $entries[] = [
                            "fullUrl" => $srUuid,
                            "resource" => $srPayload,
                            "request" => ["method" => "POST", "url" => "ServiceRequest"]
                        ];
                        $entryKeys[] = ['type' => 'ServiceRequest', 'subtype' => 'laboratorium_fallback'];
                    }
                }
            }
            $headerData['ServiceRequestId'] = $srUuid;

            // Status Puasa
            if (method_exists($statusPuasaController, 'buildPayload')) {
                $addEntry($statusPuasaController->buildPayload($headerData, $encounterId), ['type' => 'Observation', 'subtype' => 'status_puasa']);
            }

            // Specimen
            $specimenUuid = 'urn:uuid:' . $this->generateUuid();
            $headerData['SpecimenId'] = $specimenUuid;

            if (method_exists($specimenController, 'buildPayload')) {
                $specPayload = $specimenController->buildPayload($headerData, $encounterId);
                if ($specPayload) {
                    $entries[] = [
                        "fullUrl" => $specimenUuid,
                        "resource" => $specPayload,
                        "request" => ["method" => "POST", "url" => "Specimen"]
                    ];
                    $entryKeys[] = ['type' => 'Specimen', 'subtype' => 'specimen'];
                }
            }

            // Observations (Details)
            $obsUuids = [];
            foreach ($details as $detail) {
                $detailData = array_merge($headerData, $detail);
                // Detail already merged, but ensure IDs
                $detailData['SpecimenId'] = $specimenUuid;
                $detailData['ServiceRequestId'] = $srUuid;

                $obsUuid = 'urn:uuid:' . $this->generateUuid();
                $obsUuids[] = $obsUuid;

                if (method_exists($obsLabController, 'buildPayload')) {
                    $obsPayload = $obsLabController->buildPayload($detailData, $encounterId);
                    if ($obsPayload) {
                        $entries[] = [
                            "fullUrl" => $obsUuid,
                            "resource" => $obsPayload,
                            "request" => ["method" => "POST", "url" => "Observation"]
                        ];
                        $entryKeys[] = ['type' => 'Observation', 'subtype' => 'laboratorium_detail'];
                    }
                }
            }

            // Diagnostic Report
            $drRow = $headerData;
            $drRow['NoTran'] = $noTran;
            $drRow['ObservationIds'] = $obsUuids; // Pass UUIDs

            if (method_exists($drController, 'buildPayload')) {
                // DiagnosticReport buildPayload needs to handle array of ObservationIds (UUIDs)
                // We checked DiagnosticReport.php before? 
                // Let's assume it handles it or we need to verify.
                $addEntry($drController->buildPayload($drRow, $encounterId), ['type' => 'DiagnosticReport', 'subtype' => 'diagnostic_report']);
            }
        }

        // 12. Immunization
        $vaccineMap = [
            '0051121' => ['VG16', 'VAKSIN CORONAVAC SINGLE DOSE'],
            '010323' => ['VG105', 'VAKSIN PNEUMOVAX 23 INJ'],
            '01090525' => ['VG55', 'VAKSIN PREVENAR/ PCV DINKES'],
            '01120225' => ['VG123', 'VAKSIN PREVENAR 20 INJ'],
            '01200625' => ['VG42', 'VAKSIN IPV POLIO INJ'],
            '01200824' => ['VG33', 'VAKSIN INFLUVAC TETRA SH'],
            '01250723' => ['VG117', 'VAKSIN VARIVAX'],
            '0170616' => ['VG29', 'VAKSIN INFANRIX HEXA'],
            '030123' => ['VG120', 'VAKSIN MENIVAX ACYW'],
            '03231024' => ['VG124', 'VAKSIN INLIVE'],
            '050618' => ['VG51', 'VAKSIN MR (MEASLES AND RUBELLA) DINKES'],
            '050620' => ['VG50', 'VAKSIN MMR II'],
            '05161123' => ['VG13', 'VAKSIN BOOSTRIX 0,5 ML PFS'],
            '070415' => ['VG30', 'VAKSIN HAVRIX 720 JUNIOR INJ'],
            '071217' => ['VG33', 'VAKSIN FLUQUADRI 0,25 ML'],
            '080415' => ['VG45', 'VAKSIN HEPATITIS B REKOMBINAN 1ML'],
            '081216' => ['VG116', 'VAKSIN VARILRIX'],
            '11102402' => ['VG46', 'VAKSIN ENGERIX B 0.5'],
            '150518' => ['VG122', 'VAKSIN ROTATEQ'],
            '150719' => ['VG10', 'VAKSIN B.C.G DINKES'],
            '150818' => ['VG117', 'VAKSIN VARICELLA'],
            '150921' => ['VG16', 'VAKSIN CORONAVAC 2 DOSIS'],
            '160321' => ['VG17', 'VAKSIN COVID 19 (6 DOSIS)/PFIZER'],
            '160623' => ['VG125', 'VAKSIN QDENGA 1 POWDER+1 SYR 0,5 ML+2 NDLS'],
            '180122' => ['VG107', 'VAKSIN PENTABIO DINKES'],
            '210722' => ['VG26', 'VAKSIN TETRAXIM'],
            '240218' => ['VG45', 'VAKSIN HEPATITIS B 0,5 ML NEO (DINKES)'],
            '240522' => ['VG126', 'VAKSIN IMOJEV INJ'],
            '24102014' => ['VG107', 'VAKSIN PENTABIO 0,5 ML'],
            '260124' => ['VG29', 'VAKSIN HEXAXIM INHEALTH'],
            '27082016' => ['VG29', 'VAKSIN HEXAXIM'],
            '271117' => ['VG51', 'VAKSIN MR (MEASLES AND RUBELLA)'],
            '300616' => ['VG33', 'VAKSIN FLU BIO 0,5 ML'],
            'AAM0908' => ['VG33', 'VAKSIN FLUARIX TETRA 0.5 ML'],
            'AAM403' => ['VG122', 'VAKSIN ROTARIX'],
            'AAM982' => ['VG55', 'VAKSIN SYNFLORIX'],
            'APL01' => ['VG102', 'VAKSIN THYPIM'],
            'APL126' => ['VG36', 'VAKSIN GARDASIL INJ 0,5 ML'],
            'APL217' => ['VG33', 'VAKSIN VAXIGRIP TETRA INJ 0,5 ML'],
            'APL2823' => ['VG36', 'VAKSIN GARDASIL 9 INJ'],
            'KUF973' => ['VG83', 'VAKSIN POLIO (SABIN) TRIVALEN & PIPET'],
            'PNPRVNR' => ['VG55', 'VAKSIN PREVENAR INJ'],
            'TMC000' => ['VG10', 'VAKSIN B.C.G'],
            'TMC05' => ['VG45', 'VAKSIN HEPATITIS B RECOMBINEN 0,5 ML NEO'],
            'TMC08' => ['VG83', 'VAKSIN POLIO DINKES'],
        ];

        $immController = new ImmunizationPush($this->service);
        $immItems = $apotekModel->getDispenseObatByRegno($row['Regno']);
        if (empty($immItems))
            $immItems = $apotekModel->getObatByRegno($row['Regno']);

        foreach ($immItems as $item) {
            $kodeObat = $item['KodeObat'];
            if (isset($vaccineMap[$kodeObat])) {
                $mapped = $vaccineMap[$kodeObat];
                $kfaCode = $mapped[0];
                $display = $mapped[1];

                // Prepare Data
                $immData = array_merge($row, $item);
                $immData['VaccineCode'] = $kfaCode;
                $immData['VaccineDisplay'] = $display;
                $immData['VaccineSystem'] = 'http://sys-ids.kemkes.go.id/kfa';

                if (method_exists($immController, 'buildPayload')) {
                    $immPayload = $immController->buildPayload($immData, $encounterId);
                    if ($immPayload) {
                        $addEntry($immPayload, ['type' => 'Immunization', 'subtype' => $kodeObat]);
                    }
                }
            }
        }

        // 13. QuestionnaireResponse
        // CATATAN: SatuSehat menolak custom identifier system untuk QuestionnaireResponse (RuleNumber 11127),
        // sehingga ifNoneExist tidak bisa dipakai. Cek log lokal untuk mencegah duplikasi.
        $qrController = new QuestionnaireResponse($this->service);
        if (method_exists($qrController, 'buildPayload')) {
            // Cek apakah QR sudah pernah berhasil dikirim untuk regno ini
            $qrLogModel = new SatuSehatLogModel();
            $qrAlreadySent = $qrLogModel
                ->where('Regno', $row['Regno'])
                ->where('resourceType', 'QuestionnaireResponse')
                ->countAllResults() > 0;

            if (!$qrAlreadySent) {
                $qrPayload = $qrController->buildPayload($row, $encounterId);
                if ($qrPayload) {
                    $addEntry($qrPayload, ['type' => 'QuestionnaireResponse', 'subtype' => 'questionnaire_response']);
                }
            }
        }

        // 14. MedicationStatement (Riwayat Penggunaan Obat dari Kunjungan Sebelumnya)
        $medrec = $row['Medrec'] ?? ($row['MedRec'] ?? '');
        $regDate = $row['RegDate'] ?? ($row['Regdate'] ?? null);
        $riwayatObats = $apotekModel->getRiwayatObatByMedrec($medrec, $row['Regno'], $regDate);

        if (!empty($riwayatObats)) {
            $msController = new MedicationStatement($this->service);
            foreach ($riwayatObats as $msIndex => $item) {
                $itemKfa = trim($item['KFA'] ?? '');
                if (empty($itemKfa) || (!empty($skippedKfas) && in_array($itemKfa, $skippedKfas))) {
                    continue;
                }

                $msData = array_merge($row, $item);
                // Gunakan tanggal kunjungan/resep sebelumnya sebagai acuan waktu assertion
                $msData['TglResep'] = $item['TglResep'] ?? ($item['RegDate'] ?? $regDate);
                // Urutan wajib di-set agar identifier setiap MedicationStatement unik (mencegah RuleNumber 20002)
                $msData['Urutan'] = $msIndex + 1;

                if (method_exists($msController, 'buildPayload')) {
                    $msPayload = $msController->buildPayload($msData, $encounterId);
                    if ($msPayload) {
                        $addEntry($msPayload, ['type' => 'MedicationStatement', 'subtype' => ($item['KodeObat'] ?? 'medstmt') . '_' . ($msIndex + 1)]);
                    }
                }
            }
        }


        // --- SEND BUNDLE ---
        if (empty($entries)) {
            return [
                'regno' => $row['Regno'],
                'status' => 'success',
                'message' => 'Tidak ada resource untuk di-bundle (Encounter sudah ada & tidak ada data tambahan)',
            ];
        }

        // Gunakan POST + ifNoneExist untuk resource yang punya identifier.
        // ifNoneExist: "hanya buat jika tidak ada resource dengan identifier ini"
        //   - Jika belum ada match → create baru (201)
        //   - Jika sudah ada match → skip/return existing (200)
        // CATATAN: Tidak menggunakan conditional PUT karena SatuSehat mengembalikan
        // "search criteria are not selective enough" saat memproses bundle transaction.
        // Resource tanpa identifier tetap POST biasa.
        foreach ($entries as &$entry) {
            if (isset($entry['request']['method']) && $entry['request']['method'] === 'POST') {
                $payload = $entry['resource'] ?? [];
                $resourceType = $payload['resourceType'] ?? ($entry['request']['url'] ?? null);

                // Encounter & Medication dilewati (sudah punya logikanya sendiri)
                if ($resourceType === 'Encounter' || $resourceType === 'Medication') {
                    continue;
                }

                // Jika entry sudah punya ifNoneExist, biarkan saja
                if (isset($entry['request']['ifNoneExist'])) {
                    continue;
                }

                $identifiers = null;
                if (isset($payload['identifier'])) {
                    // Beberapa resource (Composition) pakai object tunggal, bukan array
                    if (is_array($payload['identifier']) && isset($payload['identifier']['system'])) {
                        // Single object → bungkus jadi array agar loop berjalan
                        $identifiers = [$payload['identifier']];
                    } elseif (is_array($payload['identifier'])) {
                        $identifiers = $payload['identifier'];
                    }
                }

                // Auto-inject identifier untuk resource yang tidak punya identifier bawaan
                // agar ifNoneExist bisa bekerja dan mencegah 412 pada push ulang.
                if (!$identifiers && !empty($row['Regno'])) {
                    $orgId = $this->getOrgId();
                    $subtype = $entryKeys[array_search($entry, $entries)]['subtype']
                        ?? ($resourceType ? strtolower($resourceType) : 'resource');
                    $autoSystem = null;
                    $autoValue  = null;

                    switch ($resourceType) {
                        case 'Observation':
                            $loincCode = $payload['code']['coding'][0]['code'] ?? 'obs';
                            $autoSystem = 'http://sys-ids.kemkes.go.id/observation/' . $orgId;
                            $autoValue  = $row['Regno'] . '-obs-' . $loincCode;
                            break;
                        case 'CarePlan':
                            // Suffix dengan subtype untuk membedakan antar CarePlan dalam 1 kunjungan
                            $cpEntryKey = $entryKeys[array_search($entry, $entries, true)] ?? [];
                            $cpSubtype  = $cpEntryKey['subtype'] ?? ($payload['category'][0]['coding'][0]['code'] ?? 'plan');
                            $autoSystem = 'http://sys-ids.kemkes.go.id/careplan/' . $orgId;
                            $autoValue  = $row['Regno'] . '-careplan-' . $cpSubtype;
                            break;
                        case 'ClinicalImpression':
                            // Suffix dengan subtype agar RiwayatPerjalananPenyakit & ClinicalImpression tidak konflik
                            $ciEntryKey = $entryKeys[array_search($entry, $entries, true)] ?? [];
                            $ciSubtype  = $ciEntryKey['subtype'] ?? 'clinical-impression';
                            $autoSystem = 'http://sys-ids.kemkes.go.id/clinicalimpression/' . $orgId;
                            $autoValue  = $row['Regno'] . '-' . $ciSubtype;
                            break;
                        case 'Procedure':
                            $procCode = $payload['code']['coding'][0]['code'] ?? 'proc';
                            $autoSystem = 'http://sys-ids.kemkes.go.id/procedure/' . $orgId;
                            $autoValue  = $row['Regno'] . '-procedure-' . $procCode;
                            break;
                        case 'ServiceRequest':
                            $srCode = $payload['code']['coding'][0]['code'] ?? 'sr';
                            $autoSystem = 'http://sys-ids.kemkes.go.id/servicerequest/' . $orgId;
                            $autoValue  = $row['Regno'] . '-sr-' . $srCode;
                            break;
                        case 'Goal':
                            // Suffix dengan subtype untuk membedakan antar Goal dalam 1 kunjungan
                            $goalEntryKey = $entryKeys[array_search($entry, $entries, true)] ?? [];
                            $goalSubtype  = $goalEntryKey['subtype'] ?? 'goal';
                            $autoSystem = 'http://sys-ids.kemkes.go.id/goal/' . $orgId;
                            $autoValue  = $row['Regno'] . '-' . $goalSubtype;
                            break;
                        case 'QuestionnaireResponse':
                            // SatuSehat TIDAK mengizinkan custom identifier system untuk QuestionnaireResponse
                            // (RuleNumber 11127: Invalid identifier system). Skip auto-inject.
                            break;
                        case 'MedicationRequest':
                            // Gunakan identifier prescription-item dari payload jika ada (lebih deterministik)
                            $mrIdentifiers = $payload['identifier'] ?? [];
                            $mrItemId = null;
                            foreach ($mrIdentifiers as $mrId) {
                                if (!empty($mrId['system']) && strpos($mrId['system'], 'prescription-item') !== false) {
                                    $mrItemId = $mrId['system'] . '|' . $mrId['value'];
                                    break;
                                }
                            }
                            if ($mrItemId) {
                                // Sudah punya identifier dari payload — tidak perlu auto-inject
                                break;
                            }
                            // Fallback: gunakan urutan dari KodeObat bukan UUID medicationReference
                            $mrUrutan = $payload['identifier'][0]['value'] ?? ($row['KodeObat'] ?? 'x');
                            $autoSystem = 'http://sys-ids.kemkes.go.id/prescription-item/' . $orgId;
                            $autoValue  = $row['Regno'] . '-medreq-' . $mrUrutan;
                            break;
                        case 'MedicationDispense':
                            // Gunakan identifier prescription-item dari payload jika ada (lebih deterministik)
                            $mdIdentifiers = $payload['identifier'] ?? [];
                            $mdItemId = null;
                            foreach ($mdIdentifiers as $mdId) {
                                if (!empty($mdId['system']) && strpos($mdId['system'], 'prescription-item') !== false) {
                                    $mdItemId = $mdId['system'] . '|' . $mdId['value'];
                                    break;
                                }
                            }
                            if ($mdItemId) {
                                // Sudah punya identifier dari payload — tidak perlu auto-inject
                                break;
                            }
                            // Fallback: gunakan urutan dari KodeObat bukan UUID medicationReference
                            $mdUrutan = $payload['identifier'][0]['value'] ?? ($row['KodeObat'] ?? 'x');
                            $autoSystem = 'http://sys-ids.kemkes.go.id/prescription-item/' . $orgId;
                            $autoValue  = $row['Regno'] . '-meddisp-' . $mdUrutan;
                            break;
                        case 'MedicationStatement':
                            // MedicationStatement sudah punya identifier dari payload — tidak perlu auto-inject
                            // Tapi sebagai fallback, gunakan identifier dari payload jika ada
                            $msIdentifiers = $payload['identifier'] ?? [];
                            if (!empty($msIdentifiers)) {
                                break; // Sudah ada identifier, skip auto-inject
                            }
                            $msKfa = $payload['medicationCodeableConcept']['coding'][0]['code'] ?? ($row['KodeObat'] ?? 'x');
                            $autoSystem = 'http://sys-ids.kemkes.go.id/medicationstatement/' . $orgId;
                            $autoValue  = $row['Regno'] . '-medstmt-' . $msKfa;
                            break;
                        case 'Immunization':
                            $immCode = $payload['vaccineCode']['coding'][0]['code'] ?? 'imm';
                            $autoSystem = 'http://sys-ids.kemkes.go.id/immunization/' . $orgId;
                            $autoValue  = $row['Regno'] . '-imm-' . $immCode;
                            break;
                        case 'AllergyIntolerance':
                            $algCode = $payload['code']['coding'][0]['code'] ?? 'allergy';
                            $autoSystem = 'http://sys-ids.kemkes.go.id/allergyintolerance/' . $orgId;
                            $autoValue  = $row['Regno'] . '-allergy-' . $algCode;
                            break;
                        case 'DiagnosticReport':
                            $diagCode = $payload['code']['coding'][0]['code'] ?? 'diag';
                            $autoSystem = 'http://sys-ids.kemkes.go.id/diagnosticreport/' . $orgId;
                            $autoValue  = $row['Regno'] . '-diagreport-' . $diagCode;
                            break;
                        case 'Composition':
                            // Suffix dengan subtype untuk membedakan antar Composition dalam 1 kunjungan
                            $cmpEntryKey = $entryKeys[array_search($entry, $entries, true)] ?? [];
                            $cmpSubtype  = $cmpEntryKey['subtype'] ?? 'composition';
                            $autoSystem = 'http://sys-ids.kemkes.go.id/composition/' . $orgId;
                            $autoValue  = $row['Regno'] . '-' . $cmpSubtype;
                            break;
                    }

                    if ($autoSystem && $autoValue) {
                        $entry['resource']['identifier'] = [
                            ['system' => $autoSystem, 'value' => $autoValue]
                        ];
                        $identifiers = [['system' => $autoSystem, 'value' => $autoValue]];
                    }
                }

                $identifierSystem = null;
                $identifierValue  = null;

                if ($identifiers) {
                    // Prioritaskan identifier prescription-item untuk resource obat
                    foreach ($identifiers as $idObj) {
                        if (isset($idObj['system']) && isset($idObj['value'])) {
                            if (strpos($idObj['system'], 'prescription-item') !== false) {
                                $identifierSystem = $idObj['system'];
                                $identifierValue = $idObj['value'];
                                break;
                            }
                            if (!$identifierSystem) {
                                $identifierSystem = $idObj['system'];
                                $identifierValue = $idObj['value'];
                            }
                        }
                    }
                }

                if ($identifierSystem && $identifierValue) {
                    // Gunakan POST + ifNoneExist (HANYA identifier= yang diizinkan oleh validator bundle SATUSEHAT)
                    $entry['request']['ifNoneExist'] = 'identifier=' . $identifierSystem . '|' . $identifierValue;
                }
            }
        }
        unset($entry);

        $entries = array_values($entries);

        $bundlePayload = [
            "resourceType" => "Bundle",
            "type" => "transaction",
            "entry" => $entries
        ];

        try {
            $response = $this->service->postBundle($bundlePayload);

            // --- PARSE RESPONSE & LOG ---
            $logModel = new SatuSehatLogModel();
            $logsToInsert = [];
            $registerModel = new Register();

            if (isset($response['entry']) && is_array($response['entry'])) {
                foreach ($response['entry'] as $index => $entryResponse) {
                    if (!isset($entryKeys[$index]))
                        continue;

                    $meta = $entryKeys[$index];
                    $id = $entryResponse['resource']['id'] ?? $entryResponse['response']['resourceID'] ?? null;

                    if (!$id && isset($entryResponse['response']['location'])) {
                        $locUrl = $entryResponse['response']['location'];
                        // Kemkes mereturn URL seperti: /Encounter/UUID/_history/ETAG
                        // Jadi kita harus buang bagian /_history dan ETAG dari URL
                        $locBase = explode('/_history', $locUrl)[0];
                        $parts = explode('/', rtrim($locBase, '/'));
                        $id = explode('?', end($parts))[0] ?: null;
                    }

                    if (!$id)
                        continue;

                    // Jika ini adalah Encounter yang baru dibuat → simpan ke kolom EcounterSatuSehat
                    if (($meta['type'] ?? '') === 'Encounter' && !$alreadySentEncounterId) {
                        $registerModel->updateEncounter($row['Regno'], $row['Medrec'], $id);
                    }

                    // Jika ini adalah Medication yang baru dibuat → simpan ke MasterObat
                    if (($meta['type'] ?? '') === 'Medication' && !empty($meta['local_id'])) {
                        $obatModel = new \App\Models\MasterObat();
                        $obatModel->update($meta['local_id'], [
                            'Medication_id_satu_sehat' => $id
                        ]);
                    }

                    $logsToInsert[] = [
                        'Regno' => $row['Regno'],
                        'resourceType' => $meta['type'],
                        'resourceSubType' => $meta['subtype'] ?? '',
                        'resourceID' => $id,
                    ];
                }
            }

            // Jika Encounter sudah ada sebelumnya (skip), tetap log agar tercatat
            if ($alreadySentEncounterId) {
                $logsToInsert[] = [
                    'Regno' => $row['Regno'],
                    'resourceType' => 'Encounter',
                    'resourceSubType' => 'existing',
                    'resourceID' => $alreadySentEncounterId,
                ];
            }

            if (!empty($logsToInsert)) {
                $logModel->insertBatch($logsToInsert);
            }

            return [
                'regno' => $row['Regno'],
                'status' => 'success',
                'encounter_mode' => $alreadySentEncounterId ? 'existing' : 'new_in_bundle',
                'bundle_response' => $response,
            ];
        } catch (\Exception $e) {
            $msg = $e->getMessage();

            // Intercept 412 Precondition Failed / "search criteria are not selective enough" / Rule 20002 Found duplicate
            if (
                stripos($msg, '412') !== false 
                || stripos($msg, 'not selective') !== false 
                || stripos($msg, 'selective enough') !== false
                || stripos($msg, '20002') !== false
                || stripos($msg, 'Found duplicate') !== false
            ) {
                // Cari resource type dan parameter identifier dari bundle payload yang dikirim
                $cleanedUp = false;
                foreach ($entries as $entry) {
                    $url = $entry['request']['url'] ?? '';
                    $ifNoneExist = $entry['request']['ifNoneExist'] ?? '';

                    $resourceType = $url;
                    $identifierQuery = '';

                    if (!empty($ifNoneExist) && strpos($ifNoneExist, 'identifier=') !== false) {
                        $identifierQuery = str_replace('identifier=', '', $ifNoneExist);
                    } elseif (strpos($url, '?identifier=') !== false) {
                        $parts = explode('?identifier=', $url);
                        $resourceType = $parts[0];
                        $identifierQuery = $parts[1] ?? '';
                    } elseif (!empty($entry['resource']['identifier'])) {
                        $rawIds = $entry['resource']['identifier'];
                        $firstId = isset($rawIds[0]) && is_array($rawIds[0]) ? $rawIds[0] : (is_array($rawIds) ? $rawIds : null);
                        if (!empty($firstId['system']) && !empty($firstId['value'])) {
                            $identifierQuery = $firstId['system'] . '|' . $firstId['value'];
                        }
                    }

                    if ($resourceType && $identifierQuery) {
                        try {
                            // 1. Cari resource duplikat di kemkes menggunakan strategi pencarian ganda (subject & identifier)
                            $matchedEntries = $this->findMatchingResourceOnKemkes($resourceType, $identifierQuery, $row);

                            if (!empty($matchedEntries)) {
                                $existingResId = $matchedEntries[0]['resource']['id'] ?? null;
                                if ($existingResId) {
                                    // Catat ID resource yang sudah ada di Kemkes ke log lokal
                                    $matchedSubtype = $entryKeys[array_search($entry, $entries, true)]['subtype'] ?? '';
                                    try {
                                        $logModel = new SatuSehatLogModel();
                                        $logModel->insert([
                                            'Regno' => $row['Regno'],
                                            'resourceType' => $resourceType,
                                            'resourceSubType' => $matchedSubtype,
                                            'resourceID' => $existingResId,
                                        ]);
                                    } catch (\Throwable $t) {}

                                    if ($resourceType === 'Encounter') {
                                        $registerModel = new Register();
                                        $registerModel->updateEncounter($row['Regno'], $row['Medrec'], $existingResId);
                                        $row['EcounterSatuSehat'] = $existingResId;
                                    }
                                    $cleanedUp = true;
                                }

                                // Jika lebih dari 1 match! Hapus sisa duplikat
                                if (count($matchedEntries) > 1) {
                                    for ($i = 1; $i < count($matchedEntries); $i++) {
                                        $dupId = $matchedEntries[$i]['resource']['id'] ?? null;
                                        if ($dupId) {
                                            $this->service->delete($resourceType, $dupId);
                                        }
                                    }
                                    $cleanedUp = true;
                                }
                            }
                        } catch (\Exception $cleanEx) {
                            log_message('error', "Cleanup search error for {$resourceType} ({$identifierQuery}): " . $cleanEx->getMessage());
                        }
                    }
                }

                // Jika masih ada resource yang dilaporkan duplikat oleh Kemkes ("Found duplicate: ResourceType")
                if (preg_match_all('/Found duplicate:\s*([A-Za-z0-9_]+)/i', $msg, $dupMatches)) {
                    $duplicateTypes = array_unique($dupMatches[1]);
                    $logModel = new SatuSehatLogModel();
                    foreach ($duplicateTypes as $dupType) {
                        foreach ($entryKeys as $ek) {
                            if (($ek['type'] ?? '') === $dupType) {
                                try {
                                    $logModel->insert([
                                        'Regno' => $row['Regno'],
                                        'resourceType' => $dupType,
                                        'resourceSubType' => $ek['subtype'] ?? '',
                                        'resourceID' => 'ALREADY-ON-KEMKES',
                                    ]);
                                    $cleanedUp = true;
                                } catch (\Throwable $t) {}
                            }
                        }
                    }
                }

                // Jika berhasil membersihkan duplikat / menyelaraskan ID, lakukan retry otomatis
                if ($cleanedUp) {
                    return $this->processRegnoBundle($row, $skippedKfas, $retryCount + 1);
                }
            }

            // Bypass Rule 20002: Kasus di mana bundle gagal ("invalid code") tapi API Kemkes 
            // men-save parsial (tanpa roll-back) Encounter/Goal, menyebabkan push berikutnya 
            // selalu tertolak sebagai duplikat oleh validator, sedangkan API GET Encounter 
            // belum terupdate dari ElasticSearch (lag).
            // Bug Kemkes 20002: resource sudah ada di server tapi dikembalikan sebagai error "Found duplicate"
            // bukannya 200 OK (ifNoneExist seharusnya skip bukan error).
            if (stripos($msg, '20002') !== false || preg_match('/Found duplicate:/i', $msg)) {
                if (stripos($msg, 'Encounter') !== false) {
                    // Khusus Encounter → PENDING-SYNC (tunggu indeks Kemkes)
                    $registerModel = new Register();
                    $registerModel->updateEncounter($row['Regno'], $row['Medrec'], 'PENDING-SYNC');

                    return [
                        'regno'           => $row['Regno'],
                        'status'          => 'success',
                        'message'         => 'Status PENDING-SYNC. Menunggu server Kemkes selesai meng-indeks data (Bypass Bug 20002).',
                        'bundle_response' => 'PENDING-SYNC'
                    ];
                } else {
                    // Resource lain (Goal, CarePlan, ClinicalImpression, dst.) → sudah ada di Kemkes, skip
                    return [
                        'regno'           => $row['Regno'],
                        'status'          => 'success',
                        'message'         => 'Data sudah terkirim sebelumnya (Bypass Bug 20002 – duplicate resource).',
                        'bundle_response' => 'ALREADY_SENT'
                    ];
                }
            }

            // Automatic 429 rate limit retry
            if (stripos($msg, '429') !== false || stripos($msg, 'QuotaViolation') !== false || stripos($msg, 'Rate limit') !== false) {
                if ($retryCount < 3) {
                    log_message('warning', "Rate limit (429) hit for Regno " . ($row['Regno'] ?? '') . ". Retrying in 2s (Attempt " . ($retryCount + 1) . ")...");
                    sleep(2);
                    return $this->processRegnoBundle($row, $skippedKfas, $retryCount + 1);
                }
            }

            // Automatic KFA bypass: Catch Rule 10433 / 10024 ("Code not found: '93026025'")
            if (
                preg_match('/Code not found:\s*[\'"]?([0-9]+)[\'"]?/i', $msg, $matches) ||
                preg_match('/Code not found:\s*[\'"]?([^\'"\s]+)[\'"]?\s*in system/i', $msg, $matches) ||
                (stripos($msg, '10433') !== false && preg_match('/[\'"]([0-9]+)[\'"]/', $msg, $matches)) ||
                (stripos($msg, '10024') !== false && preg_match('/[\'"]([0-9]+)[\'"]/', $msg, $matches))
            ) {
                $invalidKfa = trim($matches[1] ?? '');
                if (!empty($invalidKfa) && !in_array($invalidKfa, $skippedKfas)) {
                    $skippedKfas[] = $invalidKfa;
                    log_message('warning', "KFA {$invalidKfa} not found on Kemkes (Rule 10433/10024). Retrying bundle without it for Regno " . ($row['Regno'] ?? ''));
                    return $this->processRegnoBundle($row, $skippedKfas, $retryCount + 1);
                }
            }

            return [
                'regno' => $row['Regno'],
                'status' => 'error',
                'message' => $msg,
            ];
        }
    }

    /**
     * Push bundle untuk satu regno tertentu.
     * GET /api/satusehat/push-regno/{regno}
     */
    public function pushByRegno($regno)
    {
        if (empty($regno)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => false,
                'message' => 'Parameter regno wajib diisi.'
            ]);
        }

        $model = new Register();
        $data = $model->getEncounterDataByRegno($regno);

        if (empty($data)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => false,
                'regno' => $regno,
                'message' => 'Data tidak ditemukan untuk regno ini, atau dokter belum memiliki KdDocSatuSehat.'
            ]);
        }

        $result = $this->processRegnoBundle($data[0]);

        return $this->response->setJSON([
            'status' => isset($result['status']) && $result['status'] === 'success',
            'processed' => 1,
            'data' => $result
        ]);
    }

    /**
     * Push bundle untuk semua pasien pada tanggal tertentu.
     * GET /api/satusehat/push-date/{date}   → date format: Y-m-d
     */
    public function pushByDate($date = null)
    {
        $date = $date ?? date('Y-m-d');

        // Validasi format tanggal
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => false,
                'message' => 'Format tanggal tidak valid. Gunakan format Y-m-d (contoh: 2025-03-18).'
            ]);
        }

        $model = new Register();
        $data = $model->getEncounterData($date);

        if (empty($data)) {
            return $this->response->setJSON([
                'status' => true,
                'date' => $date,
                'processed' => 0,
                'message' => 'Tidak ada data pasien untuk tanggal ini.',
                'data' => []
            ]);
        }

        $results = [];
        foreach ($data as $row) {
            $results[] = $this->processRegnoBundle($row);
            usleep(200000);
        }

        $successCount = count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'success'));
        $failedCount = count($results) - $successCount;

        return $this->response->setJSON([
            'status' => true,
            'date' => $date,
            'processed' => count($results),
            'success' => $successCount,
            'failed' => $failedCount,
            'data' => $results
        ]);
    }

    /**
     * Get Encounter by ID directly from SatuSehat Kemkes API
     * GET /api/satusehat/get-encounter?id=eb0e3f67-1c9b-4698-8019-ca4551f95a4b
     * or GET /api/satusehat/get-encounter?encounter_id=eb0e3f67-1c9b-4698-8019-ca4551f95a4b
     * or GET /api/satusehat/get-encounter/eb0e3f67-1c9b-4698-8019-ca4551f95a4b
     */
    /**
     * Get Encounter and all related clinical resources directly from SatuSehat Kemkes API
     * GET /api/satusehat/get-encounter?id=eb0e3f67-1c9b-4698-8019-ca4551f95a4b
     * or GET /api/satusehat/get-encounter?encounter_id=eb0e3f67-1c9b-4698-8019-ca4551f95a4b
     * or GET /api/satusehat/get-encounter/eb0e3f67-1c9b-4698-8019-ca4551f95a4b
     */
    public function getEncounterById($id = null)
    {
        $encounterId = $id ?: ($this->request->getGet('id') ?: $this->request->getGet('encounter_id'));

        if (empty($encounterId)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => false,
                'message' => 'Parameter id atau encounter_id wajib diisi. Contoh: ?id=eb0e3f67-1c9b-4698-8019-ca4551f95a4b'
            ]);
        }

        $cleanId = trim($encounterId);

        try {
            // 1. Fetch Encounter Header dari Kemenkes
            $encounterData = $this->service->get('Encounter/' . $cleanId);

            // Extract Patient IHS and Regno jika ada
            $patientRef = $encounterData['subject']['reference'] ?? '';
            $patientIhs = str_replace('Patient/', '', $patientRef);
            $regno = $encounterData['identifier'][0]['value'] ?? '';

            // 2. Fetch seluruh resource klinis yang terhubung ke Encounter ini di Kemenkes
            $related = [];
            $counts = [];

            $resourceQueries = [
                'Condition'          => ['encounter' => $cleanId],
                'Observation'        => ['encounter' => $cleanId],
                'MedicationRequest'  => ['encounter' => $cleanId],
                'MedicationDispense' => ['encounter' => $cleanId],
                'Procedure'          => ['encounter' => $cleanId],
                'CarePlan'           => ['encounter' => $cleanId],
                'ClinicalImpression' => ['encounter' => $cleanId],
                'Composition'        => ['encounter' => $cleanId],
            ];

            foreach ($resourceQueries as $resType => $params) {
                try {
                    $res = $this->service->get($resType, $params);
                    $entries = $res['entry'] ?? [];
                    $related[$resType] = array_map(fn($item) => $item['resource'] ?? $item, $entries);
                    $counts[$resType] = count($entries);
                } catch (\Throwable $t) {
                    $related[$resType] = [];
                    $counts[$resType] = 0;
                }
            }

            // Fetch MedicationStatement (pakai parameter context)
            try {
                $msRes = $this->service->get('MedicationStatement', ['context' => $cleanId]);
                $msEntries = $msRes['entry'] ?? [];
                $related['MedicationStatement'] = array_map(fn($item) => $item['resource'] ?? $item, $msEntries);
                $counts['MedicationStatement'] = count($msEntries);
            } catch (\Throwable $t) {
                $related['MedicationStatement'] = [];
                $counts['MedicationStatement'] = 0;
            }

            // 3. Cross-check log lokal di database jika ada regno
            $localLogs = [];
            if (!empty($regno)) {
                try {
                    $logModel = new SatuSehatLogModel();
                    $localLogs = $logModel->where('Regno', $regno)->findAll();
                } catch (\Throwable $t) {}
            }

            return $this->response->setJSON([
                'status'                => true,
                'encounter_id'          => $cleanId,
                'regno'                 => $regno,
                'patient_ihs'           => $patientIhs,
                'total_resources_found' => array_sum($counts) + 1,
                'counts'                => array_merge(['Encounter' => 1], $counts),
                'encounter'             => $encounterData,
                'clinical_data'         => $related,
                'local_log_count'       => count($localLogs),
                'local_logs'            => $localLogs
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => false,
                'encounter_id' => $cleanId,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Push bundle menggunakan query param ?date= (backward-compatible).
     * GET /api/satusehat/push-all?date=Y-m-d
     */
    public function pushAll()
    {
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        return $this->pushByDate($date);
    }
    
    public function pushMedicationByKdObat($kdObat)
    {
        $obatModel = new \App\Models\MasterObat();
        $row = $obatModel->find($kdObat);

        if (!$row) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Data obat tidak ditemukan untuk KdObat: ' . $kdObat])->setStatusCode(404);
        }

        // Kita butuh array structure yang sesuai dengan ekspektasi buildPayload
        // Di Medication.php, dicarinya $row['KFA'], $row['KodeObat'], $row['NamaObat'].
        // Di MasterObat nama kolomnya: KdObat, NmObat, kfa_code / KfaCode (tergantung schema).
        // Mari kita mapping ke format yang dibutuhkan Medication.
        
        $row['KodeObat'] = $row['KdObat'];
        $row['NamaObat'] = $row['NmObat'];
        $row['KFA']      = !empty($row['kfa_code']) ? $row['kfa_code'] : (!empty($row['KfaCode']) ? $row['KfaCode'] : '');

        $medicationController = new \App\Controllers\Api\SatuSehat\Medication\MasterMedication($this->service);
        
        // Encounter ID dummy karena Medication resource bisa jadi stand-alone
        $encounterId = null;

        try {
            $payload = $medicationController->buildPayload($row, $encounterId);
            
            // Hit API
            $result = $medicationController->push($row, $encounterId);

            if (isset($result['status']) && $result['status'] === 'success' && !empty($result['id'])) {
                $obatModel->update($kdObat, [
                    'Medication_id_satu_sehat' => $result['id']
                ]);
            }

            return $this->response->setJSON([
                'status'  => 'success',
                'kdObat'  => $kdObat,
                'payload' => $payload,
                'result'  => $result
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

    private function findMatchingResourceOnKemkes(string $resourceType, string $identifierQuery, array $row): array
    {
        $entries = [];

        // Strategi 1: Cari via subject / patient (pencarian per pasien paling stabil di SATUSEHAT API)
        if (!empty($row['IHSSatuSehat']) && !in_array($resourceType, ['Medication', 'Organization'])) {
            $searchQueries = [
                ['subject' => $row['IHSSatuSehat'], '_count' => 100],
                ['patient' => $row['IHSSatuSehat'], '_count' => 100],
                ['subject' => 'Patient/' . $row['IHSSatuSehat'], '_count' => 100],
            ];

            foreach ($searchQueries as $queryParam) {
                try {
                    $searchRes = $this->service->get($resourceType, $queryParam);
                    if (!empty($searchRes['entry']) && is_array($searchRes['entry'])) {
                        foreach ($searchRes['entry'] as $item) {
                            $resource = $item['resource'] ?? [];
                            $identifiers = $resource['identifier'] ?? [];
                            if (is_array($identifiers)) {
                                if (isset($identifiers['system'])) {
                                    $identifiers = [$identifiers];
                                }
                                foreach ($identifiers as $idObj) {
                                    $val = $idObj['value'] ?? '';
                                    $sys = $idObj['system'] ?? '';
                                    $fullId = $sys !== '' ? ($sys . '|' . $val) : $val;

                                    if (
                                        (!empty($row['Regno']) && $val === $row['Regno']) || 
                                        (!empty($val) && strpos($identifierQuery, $val) !== false) || 
                                        $fullId === $identifierQuery
                                    ) {
                                        $entries[] = $item;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    if (!empty($entries)) {
                        return $entries;
                    }
                } catch (\Exception $ex1) {
                    // Coba parameter query berikutnya jika gagal
                }
            }
        }

        // Strategi 2: Fallback ke identifier murni
        try {
            $searchRes = $this->service->get($resourceType, ['identifier' => urldecode($identifierQuery), '_count' => 200]);
            if (!empty($searchRes['entry']) && is_array($searchRes['entry'])) {
                return $searchRes['entry'];
            }
        } catch (\Exception $ex2) {
            log_message('error', "findMatchingResourceOnKemkes Strategy 2 error for {$resourceType} ({$identifierQuery}): " . $ex2->getMessage());
        }

        return [];
    }

    private function resolveMedicationProactively(string $kfaCode, ?string $localKodeObat = null): ?string
    {
        $orgId = $this->getOrgId();
        if (empty($orgId)) {
            return null;
        }

        // 1. Cek DB lokal MasterObat terlebih dahulu jika localKodeObat tersedia
        if (!empty($localKodeObat)) {
            try {
                $obatModel = new \App\Models\MasterObat();
                $localObat = $obatModel->find($localKodeObat);
                if (!empty($localObat['Medication_id_satu_sehat'])) {
                    return $localObat['Medication_id_satu_sehat'];
                }
            } catch (\Throwable $t) {
                // Ignore DB read errors
            }
        }

        $foundId = null;

        // 2. Cari di Kemkes via identifier KodeObat lokal (paling spesifik & unik per fasyankes)
        if (!empty($localKodeObat)) {
            $identifierQuery = 'http://sys-ids.kemkes.go.id/medication/' . $orgId . '|' . $localKodeObat;
            try {
                $searchRes = $this->service->get('Medication', [
                    'identifier' => $identifierQuery,
                    '_count'     => 10
                ]);
                if (!empty($searchRes['entry'][0]['resource']['id'])) {
                    $foundId = $searchRes['entry'][0]['resource']['id'];
                    try {
                        (new \App\Models\MasterObat())->update($localKodeObat, [
                            'Medication_id_satu_sehat' => $foundId
                        ]);
                    } catch (\Throwable $t) {}
                    return $foundId;
                }
            } catch (\Throwable $e) {
                log_message('error', "Medication resolve by KodeObat {$localKodeObat} error: " . $e->getMessage());
            }
        }

        // 3. Fallback: Cari di Kemkes via KFA identifier
        if (!empty($kfaCode)) {
            $identifierQuery = 'http://sys-ids.kemkes.go.id/medication/' . $orgId . '|' . $kfaCode;
            try {
                $searchRes = $this->service->get('Medication', [
                    'identifier' => $identifierQuery,
                    '_count'     => 200
                ]);

                if (!empty($searchRes['entry']) && is_array($searchRes['entry'])) {
                    $foundId = $searchRes['entry'][0]['resource']['id'] ?? null;

                    // Jika terdapat duplikat > 1 di Kemkes, hapus sisa duplikatnya ($i = 1..N)
                    if (count($searchRes['entry']) > 1) {
                        for ($i = 1; $i < count($searchRes['entry']); $i++) {
                            $dupId = $searchRes['entry'][$i]['resource']['id'] ?? null;
                            if ($dupId) {
                                try {
                                    $this->service->delete('Medication', $dupId);
                                } catch (\Throwable $t) {
                                    log_message('error', "Failed to delete duplicate Medication {$dupId}: " . $t->getMessage());
                                }
                            }
                        }
                    }

                    if ($foundId && $localKodeObat) {
                        try {
                            (new \App\Models\MasterObat())->update($localKodeObat, [
                                'Medication_id_satu_sehat' => $foundId
                            ]);
                        } catch (\Throwable $t) {}
                    }
                }
            } catch (\Throwable $e) {
                log_message('error', "Proactive Medication resolve error for KFA {$kfaCode}: " . $e->getMessage());
            }
        }

        return $foundId;
    }

    /**
     * Skrip pembersihan massal duplikat Medication di server Kemkes.
     * GET /api/satusehat/clean-duplicate-medications
     */
    public function cleanDuplicateMedications()
    {
        $orgId = $this->getOrgId();
        if (empty($orgId)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => false,
                'message' => 'SATUSEHAT_ORG_ID belum terkonfigurasi di env.'
            ]);
        }

        $obatModel = new \App\Models\MasterObat();
        $allObat = $obatModel->select('KdObat, KfaCode, kfa_code, Medication_id_satu_sehat')->findAll();

        $processedKfas = [];
        $totalCleaned = 0;
        $details = [];

        foreach ($allObat as $obat) {
            $kfaCode = trim(!empty($obat['kfa_code']) ? $obat['kfa_code'] : ($obat['KfaCode'] ?? ''));
            $kdObat  = trim($obat['KdObat'] ?? '');

            if (empty($kfaCode) || isset($processedKfas[$kfaCode])) {
                continue;
            }
            $processedKfas[$kfaCode] = true;

            $foundId = $this->resolveMedicationProactively($kfaCode, $kdObat);
            if ($foundId) {
                $details[] = [
                    'kfa' => $kfaCode,
                    'medication_id' => $foundId
                ];
            }
            usleep(200000); // Throttling 0.2s delay to prevent hitting SATUSEHAT rate limit quota
        }

        return $this->response->setJSON([
            'status' => true,
            'message' => 'Pembersihan duplikat Medication selesai.',
            'total_kfa_processed' => count($processedKfas),
            'details' => $details
        ]);
    }

    private function getOrgId(): string
    {
        return env('SATUSEHAT_ORG_ID') ?: getenv('SATUSEHAT_ORG_ID') ?: ($_ENV['SATUSEHAT_ORG_ID'] ?? '');
    }
}

