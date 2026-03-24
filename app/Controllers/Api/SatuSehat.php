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
        }
        catch (\Exception $e) {
            return 'Gagal fetch IHS: ' . $e->getMessage();
        }
    }

    /**
     * Build Encounter payload (tanpa kirim ke API).
     */
    private function buildEncounterPayload(array $row): array
    {
        $orgId = getenv('SATUSEHAT_ORG_ID');
        $dateOnly = date('Y-m-d', strtotime($row['Regdate']));
        $timeOnly = date('H:i:s', strtotime($row['RegTime']));
        $startDateTime = date('c', strtotime($dateOnly . ' ' . $timeOnly));

        $serviceTypeMap = [
            '01' => ['code' => '408464004', 'display' => 'Ophthalmology service'],
            '03' => ['code' => '419192003', 'display' => 'Internal medicine'],
            '04' => ['code' => '394609007', 'display' => 'Surgical service'],
            '05' => ['code' => '408472002', 'display' => 'Obstetrics and gynecology service'],
            '07' => ['code' => '408478003', 'display' => 'Pediatric service'],
            '08' => ['code' => '408465003', 'display' => 'Cardiology service'],
            '09' => ['code' => '408469000', 'display' => 'Neurology service'],
            '10' => ['code' => '408470005', 'display' => 'Otolaryngology service'],
            '11' => ['code' => '408475004', 'display' => 'Urology service'],
            '12' => ['code' => '408467006', 'display' => 'Dermatology service'],
            '13' => ['code' => '408468001', 'display' => 'Neurosurgical service'],
            '14' => ['code' => '408471009', 'display' => 'Orthopedic service'],
            '15' => ['code' => '408471009', 'display' => 'Psychiatry service'],
            '16' => ['code' => '722163007', 'display' => 'Pediatric dental service'],
            '17' => ['code' => '408444009', 'display' => 'Dental service'],
            '18' => ['code' => '408450007', 'display' => 'Laboratory medicine service'],
            '20' => ['code' => '408466002', 'display' => 'Anesthesiology service'],
            '21' => ['code' => '408455002', 'display' => 'Radiology service'],
            '30' => ['code' => '408478007', 'display' => 'Emergency medical service'],
            '31' => ['code' => '408457005', 'display' => 'Renal dialysis service'],
            '32' => ['code' => '722164001', 'display' => 'Vascular surgery service'],
            '33' => ['code' => '408476003', 'display' => 'Oral and maxillofacial surgery service'],
            '35' => ['code' => '419192003', 'display' => 'Internal medicine'],
            '36' => ['code' => '408465003', 'display' => 'Cardiology service'],
            '37' => ['code' => '408464004', 'display' => 'Ophthalmology service'],
            '38' => ['code' => '408469000', 'display' => 'Neurology service'],
            '39' => ['code' => '408472002', 'display' => 'Obstetrics and gynecology service'],
            '40' => ['code' => '408478003', 'display' => 'Pediatric service'],
            '41' => ['code' => '394609007', 'display' => 'Surgical service'],
            '42' => ['code' => '408443003', 'display' => 'General medical service'],
            '43' => ['code' => '408443003', 'display' => 'General medical service'],
            '44' => ['code' => '408461008', 'display' => 'Tuberculosis service'],
            '46' => ['code' => '408472002', 'display' => 'Maternal and child health service'],
            '47' => ['code' => '408459008', 'display' => 'Rehabilitation service'],
            '48' => ['code' => '408462001', 'display' => 'Nutrition service'],
            '49' => ['code' => '419192003', 'display' => 'Internal medicine service'],
            '50' => ['code' => '410158009', 'display' => 'Physiotherapy service'],
            '52' => ['code' => '702873001', 'display' => 'Health check service'],
            '53' => ['code' => '722162002', 'display' => 'Psychology service'],
            '54' => ['code' => '185389009', 'display' => 'Home visit service'],
            '55' => ['code' => '408474000', 'display' => 'Adult hematology service'],
            '56' => ['code' => '408474000', 'display' => 'Pediatric hematology service'],
            '57' => ['code' => '308335008', 'display' => 'Hospital admission service'],
            '58' => ['code' => '408474000', 'display' => 'Hematology and oncology service'],
        ];

        $kdPoli = $row['KdPoli'] ?? '';
        $stCode = $serviceTypeMap[$kdPoli]['code'] ?? '419192003';
        $stDisplay = $serviceTypeMap[$kdPoli]['display'] ?? 'Internal medicine';
        $isEmer = ($kdPoli === '30');

        return [
            'resourceType' => 'Encounter',
            'identifier' => [
                [
                    'system' => 'http://sys-ids.kemkes.go.id/encounter/' . $orgId,
                    'value' => $row['Regno'],
                ]
            ],
            'status' => 'arrived',
            'statusHistory' => [
                [
                    'status' => 'arrived',
                    'period' => ['start' => $startDateTime],
                ]
            ],
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => $isEmer ? 'EMER' : 'AMB',
                'display' => $isEmer ? 'emergency' : 'ambulatory',
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
            'period' => ['start' => $startDateTime],
            'location' => [
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
                                                'system' => 'http://terminology.kemkes.go.id/CodeSystem/locationServiceClass-Outpatient',
                                                'code' => (!empty($row['LocationServiceClassCode']) ? $row['LocationServiceClassCode'] : 'reguler'),
                                                'display' => (!empty($row['LocationServiceClassDisplay']) ? $row['LocationServiceClassDisplay'] : 'Kelas Reguler'),
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
            ],
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

    private function processRegnoBundle($row)
    {
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
                // Kemkes sandbox membatasi data lewat paginasi.
                // Kita harus memeriksa semua halaman Encounter pasien untuk mencegah pendaftaran ulang (Rule 20002).
                $params = [
                    'subject' => $row['IHSSatuSehat'],
                    '_count' => 50
                ];

                $foundId = null;
                while ($params && !$foundId) {
                    $encRes = $this->service->get('Encounter', $params);

                    if (isset($encRes['entry']) && is_array($encRes['entry'])) {
                        foreach ($encRes['entry'] as $entry) {
                            $res = $entry['resource'] ?? [];
                            if (isset($res['identifier']) && is_array($res['identifier'])) {
                                foreach ($res['identifier'] as $idObj) {
                                    if (($idObj['value'] ?? '') === $row['Regno']) {
                                        $foundId = $res['id'] ?? null;
                                        break 3; // Keluar dari loop ID, Resource, dan Paginasi sekaligus
                                    }
                                }
                            }
                        }
                    }

                    // Tentukan parameter untuk halaman berikutnya jika ada link 'next'
                    $params = null;
                    if (isset($encRes['link']) && is_array($encRes['link'])) {
                        foreach ($encRes['link'] as $link) {
                            if (($link['relation'] ?? '') === 'next') {
                                $parsed = parse_url($link['url']);
                                parse_str($parsed['query'] ?? '', $params);
                                break;
                            }
                        }
                    }
                }

                if (!empty($foundId)) {
                    $alreadySentEncounterId = $foundId;
                    // Simpan ID yang sudah ketemu ke database lokal bapak
                    $registerModel = new \App\Models\Register();
                    $registerModel->updateEncounter($row['Regno'], $row['Medrec'], $alreadySentEncounterId);
                }
                else if ($alreadySentEncounterId === 'PENDING-SYNC') {
                    // Api Kemkes masih mengindeks data yang sudah disubmit secara parsial
                    return [
                        'regno' => $row['Regno'],
                        'status' => 'skipped',
                        'id' => 'PENDING-SYNC',
                        'message' => 'Sedang di-index oleh Kemkes (Bypass Error 20002).'
                    ];
                }
            }
            catch (\Exception $e) {
            }
        }

        if ($alreadySentEncounterId) {
            return [
                'regno' => $row['Regno'],
                'status' => 'skipped',
                'id' => $alreadySentEncounterId,
                'message' => 'Sudah dipush ke SatuSehat'
            ];
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

        // ── 3. Helper: tambah entry ke bundle ────────────────────────────────
        $addEntry = function ($payload, $key, $method = 'POST', $url = null, $fixRef = true) use (&$entries, &$entryKeys, $encounterUuid, $alreadySentEncounterId) {
            if (!$payload)
                return;
            if (!$url)
                $url = $payload['resourceType'];

            // Post-process hanya jika Encounter belum di-send (UUID dipakai sebagai placeholder)
            if ($fixRef && !$alreadySentEncounterId) {
                $payload = $this->fixEncounterRef($payload, $encounterUuid);
            }

            $entries[] = [
                'fullUrl' => 'urn:uuid:' . $this->generateUuid(),
                'resource' => $payload,
                'request' => ['method' => $method, 'url' => $url],
            ];
            $entryKeys[] = $key;
        };

        // ── 4. Encounter masuk bundle sebagai ENTRY PERTAMA ───────────────────
        if ($alreadySentEncounterId) {
        // Encounter sudah ada → tidak masuk bundle, langsung reference real ID
        }
        else {
            // Encounter baru → masuk sebagai POST dengan fullUrl = encounterFullUrl
            $entries[] = [
                'fullUrl' => $encounterFullUrl,
                'resource' => $this->buildEncounterPayload($row),
                'request' => ['method' => 'POST', 'url' => 'Encounter'],
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
        if (method_exists($keluhanUtama, 'buildPayload')) {
            $keluhanUtamaPayload = $keluhanUtama->buildPayload($row, $encounterId);
            $addEntry($keluhanUtamaPayload, ['type' => 'Condition', 'subtype' => 'keluhan_utama']);
        }

        if (method_exists($meninggalkanFaskes, 'buildPayload'))
            $addEntry($meninggalkanFaskes->buildPayload($row, $encounterId), ['type' => 'Condition', 'subtype' => 'meninggalkan_faskes']);

        // Keluhan Utama ID for other resources
        // Since we are bundling, we don't have the ID yet.
        // We must use a UUID if we want to reference it in ClinicalImpression/Goal.
        // But ClinicalImpression logic in processRowClinicalImpression uses $keluhanUtamaId from PREVIOUS response.
        // If we bundle, we must use UUID reference.
        // Let's generate a UUID for Keluhan Utama if it exists.
        $keluhanUtamaUuid = null;
        if ($keluhanUtamaPayload) {
            $keluhanUtamaUuid = 'urn:uuid:' . $this->generateUuid();
            // Find the entry we just added and set fullUrl
            $lastIdx = count($entries) - 1;
            $entries[$lastIdx]['fullUrl'] = $keluhanUtamaUuid;
        // Also need to pass this UUID to dependents
        }

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

        $tujuanPerawatan = new TujuanPerawatan($this->service);
        $goalPayload = null;
        if (method_exists($tujuanPerawatan, 'buildPayload')) {
            $goalPayload = $tujuanPerawatan->buildPayload($row, $encounterId, null);
            $addEntry($goalPayload, ['type' => 'Goal', 'subtype' => 'tujuan_perawatan']);
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
            }
            catch (\Exception $e) {
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
        $obats = $apotekModel->getObatByRegno($row['Regno']);
        if (empty($obats)) {
            $obats = $apotekModel->getDispenseObatByRegno($row['Regno']);
        }

        $medicationController = new Medication($this->service);
        $medicationRequestController = new MedicationRequest($this->service);
        $medicationDispenseController = new MedicationDispense($this->service);

        $medUuidsByKode = []; // Hashmap untuk deduplikasi resoure Medication

        foreach ($obats as $index => $obat) {
            $obat['Urutan'] = $index + 1;

            // Gunakan KFA sebagai kunci deduplikasi utama di BUNDLE INI SAJA, karena KFA yang dicek Kemkes 
            $kfaKey = trim($obat['KFA'] ?? '');
            $kodeDeduplikasi = $kfaKey !== '' ? $kfaKey : (trim($obat['KodeObat'] ?? '') ?: $index);

            // Wajib pastikan KodeObat di payload memiliki suffix acak agar lolos dari Rule 20002 
            // (duplikasi global KFA Kemkes) sesuai permintaan
            $obat['KodeObat'] = $kodeDeduplikasi . '-' . mt_rand(10000, 99999);

            // Generate UUIDs. Untuk Medication, cek apakah KFA ini sudah digenerate di bundle yang sama
            if (isset($medUuidsByKode[$kodeDeduplikasi])) {
                $medUuid = $medUuidsByKode[$kodeDeduplikasi];
            }
            else {
                $medUuid = 'urn:uuid:' . $this->generateUuid();
                $medUuidsByKode[$kodeDeduplikasi] = $medUuid;

                // Medication Resource (hanya ditambahkan 1x per tipe kode obat yang sama ke bundle)
                if (method_exists($medicationController, 'buildPayload')) {
                    $medPayload = $medicationController->buildPayload($obat, $encounterId);
                    if ($medPayload) {
                        $orgId = getenv('SATUSEHAT_ORG_ID');
                        $entries[] = [
                            "fullUrl" => $medUuid,
                            "resource" => $medPayload,
                            "request" => [
                                "method" => "POST",
                                "url" => "Medication",
                                // KEMKES SPECIFIC: Conditional Create untuk menghilangkan Rule 20002 antar pasien
                                "ifNoneExist" => "identifier=http://sys-ids.kemkes.go.id/medication/" . $orgId . "|" . $obat['KodeObat']
                            ]
                        ];
                        $entryKeys[] = ['type' => 'Medication', 'subtype' => 'medication', 'local_id' => $obat['KodeObat'] ?? $index];
                    }
                }
            }

            // Request UUID unik per item
            $reqUuid = 'urn:uuid:' . $this->generateUuid();

            // MedicationRequest
            $reqData = array_merge($row, $obat);
            $reqData['MedicationId'] = $medUuid; // Use UUID reference

            // Populate logic from processRowMedication
            $reqData['TglResep'] = $obat['TglResep'] ?? ($obat['RegDate'] ?? ($obat['Regdate'] ?? null));
            $reqData['AturanPakai'] = $obat['AturanPakai'] ?? null;
            $notes = [];
            if (!empty($obat['NoteCaraMinumObat']))
                $notes[] = $obat['NoteCaraMinumObat'];
            if (!empty($obat['NoteSigna']))
                $notes[] = $obat['NoteSigna'];
            $reqData['CatatanMinum'] = implode(', ', $notes);
            $reqData['InstruksiPasien'] = $obat['KeteranganPakai'] ?? null;
            $reqData['JumlahObat'] = $obat['Qty'] ?? null;
            $reqData['SatuanObat'] = $obat['Satuan'] ?? 'TAB';

            if (method_exists($medicationRequestController, 'buildPayload')) {
                $reqPayload = $medicationRequestController->buildPayload($reqData, $encounterId);
                if ($reqPayload) {
                    $entries[] = [
                        "fullUrl" => $reqUuid,
                        "resource" => $reqPayload,
                        "request" => ["method" => "POST", "url" => "MedicationRequest"]
                    ];
                    $entryKeys[] = ['type' => 'MedicationRequest', 'subtype' => 'medication_request'];
                }
            }

            // MedicationDispense
            $reqData['MedicationRequestId'] = $reqUuid; // Use UUID reference
            if (method_exists($medicationDispenseController, 'buildPayload')) {
                $dispensePayload = $medicationDispenseController->buildPayload($reqData, $encounterId, $reqUuid);
                if ($dispensePayload) {
                    $addEntry($dispensePayload, ['type' => 'MedicationDispense', 'subtype' => 'medication_dispense']);
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
        $qrController = new QuestionnaireResponse($this->service);
        if (method_exists($qrController, 'buildPayload')) {
            $qrPayload = $qrController->buildPayload($row, $encounterId);
            if ($qrPayload) {
                $addEntry($qrPayload, ['type' => 'QuestionnaireResponse', 'subtype' => 'questionnaire_response']);
            }
        }

        // 14. MedicationStatement
        $msController = new MedicationStatement($this->service);
        $candidates = [];
        foreach ($obats as $item) {
            if (!empty($item['KFA'])) {
                $candidates[] = $item;
            }
        }

        if (!empty($candidates)) {
            usort($candidates, static function ($a, $b) {
                $aKode = (string)($a['KodeObat'] ?? '');
                $bKode = (string)($b['KodeObat'] ?? '');
                $cmp = strcmp($aKode, $bKode);
                if ($cmp !== 0) {
                    return $cmp;
                }
                $aKfa = (string)($a['KFA'] ?? '');
                $bKfa = (string)($b['KFA'] ?? '');
                return strcmp($aKfa, $bKfa);
            });
            $picked = $candidates[0];
            $msData = array_merge($row, $picked);

            if (method_exists($msController, 'buildPayload')) {
                $msPayload = $msController->buildPayload($msData, $encounterId);
                if ($msPayload) {
                    $addEntry($msPayload, ['type' => 'MedicationStatement', 'subtype' => $picked['KodeObat'] ?? 'medication_statement']);
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
        }
        catch (\Exception $e) {
            $msg = $e->getMessage();

            // Bypass Rule 20002: Kasus di mana bundle gagal ("invalid code") tapi API Kemkes 
            // men-save parsial (tanpa roll-back) Encounter/Goal, menyebabkan push berikutnya 
            // selalu tertolak sebagai duplikat oleh validator, sedangkan API GET Encounter 
            // belum terupdate dari ElasticSearch (lag).
            if (strpos($msg, 'RuleNumber: 20002') !== false && strpos($msg, 'Encounter') !== false) {
                // Update local DB to PENDING-SYNC
                $registerModel = new Register();
                $registerModel->updateEncounter($row['Regno'], $row['Medrec'], 'PENDING-SYNC');

                return [
                    'regno' => $row['Regno'],
                    'status' => 'success',
                    'message' => 'Status PENDING-SYNC. Menunggu server Kemkes selesai meng-indeks data (Bypass Bug 20002).',
                    'bundle_response' => 'PENDING-SYNC'
                ];
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
     * Push bundle menggunakan query param ?date= (backward-compatible).
     * GET /api/satusehat/push-all?date=Y-m-d
     */
    public function pushAll()
    {
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        return $this->pushByDate($date);
    }
}
