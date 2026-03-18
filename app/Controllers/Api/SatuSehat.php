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


    private function sendEncounter($row, $model)
    {
        $orgId = getenv('SATUSEHAT_ORG_ID');
        if (!$orgId) return ['status' => false, 'message' => 'SATUSEHAT_ORG_ID missing'];

        if (!empty($row['EcounterSatuSehat'])) {
            return [
                'status' => 'skipped',
                'id' => $row['EcounterSatuSehat'],
                'message' => 'Sudah ada'
            ];
        }
        if (empty($row['IHSSatuSehat'])) {
            return ['status' => false, 'message' => 'IHSSatuSehat missing'];
        }
        if (empty($row['KdDocSatuSehat'])) {
            return ['status' => false, 'message' => 'KdDocSatuSehat missing'];
        }
        if (empty($row['IdRuanganKemenkes'])) {
            return ['status' => false, 'message' => 'IdRuanganKemenkes missing'];
        }

        try {
            $dateOnly = date('Y-m-d', strtotime($row['Regdate']));
            $timeOnly = date('H:i:s', strtotime($row['RegTime']));
            $dateTimeStr = $dateOnly . ' ' . $timeOnly;
            $startDateTime = date('c', strtotime($dateTimeStr));
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
                '58' => ['code' => '408474000', 'display' => 'Hematology and oncology service']
            ];
            $kdPoli = $row['KdPoli'] ?? '';
            $stCode = $serviceTypeMap[$kdPoli]['code'] ?? '419192003';
            $stDisplay = $serviceTypeMap[$kdPoli]['display'] ?? 'Internal medicine';

            $payload = [
                "resourceType" => "Encounter",
                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/encounter/" . $orgId,
                        "value" => $row['Regno']
                    ]
                ],
                "status" => "arrived",
                "statusHistory" => [
                    [
                        "status" => "arrived",
                        "period" => [
                            "start" => $startDateTime
                        ]
                    ]
                ],
                "class" => [
                    "system" => "http://terminology.hl7.org/CodeSystem/v3-ActCode",
                    "code" => ($row['KdPoli'] == '30') ? 'EMER' : 'AMB',
                    "display" => ($row['KdPoli'] == '30') ? 'emergency' : 'ambulatory'
                ],
                "serviceType" => [
                    "coding" => [
                        [
                            "system" => "http://snomed.info/sct",
                            "code" => $stCode,
                            "display" => $stDisplay
                        ]
                    ]
                ],
                "subject" => [
                    "reference" => "Patient/" . $row['IHSSatuSehat'],
                    "display" => $row['Firstname']
                ],
                "participant" => [
                    [
                        "type" => [
                            [
                                "coding" => [
                                    [
                                        "system" => "http://terminology.hl7.org/CodeSystem/v3-ParticipationType",
                                        "code" => "ATND",
                                        "display" => "attender"
                                    ]
                                ]
                            ]
                        ],
                        "individual" => [
                            "reference" => "Practitioner/" . $row['KdDocSatuSehat'],
                            "display" => $row['NmDoc']
                        ]
                    ]
                ],
                "period" => [
                    "start" => $startDateTime
                ],
                "location" => [
                    [
                        "location" => [
                            "reference" => "Location/" . $row['IdRuanganKemenkes'],
                            "display" => $row['NmRuanganKemenkes']
                        ],
                        "period" => [
                            "start" => $startDateTime
                        ],
                        "extension" => [
                            [
                                "url" => "https://fhir.kemkes.go.id/r4/StructureDefinition/ServiceClass",
                                "extension" => [
                                    [
                                        "url" => "value",
                                        "valueCodeableConcept" => [
                                            "coding" => [
                                                [
                                                    "system" => "http://terminology.kemkes.go.id/CodeSystem/locationServiceClass-Outpatient",
                                                    "code" => $row['LocationServiceClassCode'] ?? 'reguler',
                                                    "display" => $row['LocationServiceClassDisplay'] ?? 'Kelas Reguler'
                                                ]
                                            ]
                                        ]
                                    ],
                                    [
                                        "url" => "upgradeClassIndicator",
                                        "valueCodeableConcept" => [
                                            "coding" => [
                                                [
                                                    "system" => "http://terminology.kemkes.go.id/CodeSystem/locationUpgradeClass",
                                                    "code" => $row['LocationUpgradeClassCode'] ?? 'kelas-tetap',
                                                    "display" => $row['LocationUpgradeClassDisplay'] ?? 'Kelas Tetap Perawatan'
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                "serviceProvider" => [
                    "reference" => "Organization/" . $orgId
                ]
            ];

            $response = $this->service->post('Encounter', $payload);

            if (isset($response['id'])) {
                $model->updateEncounter($row['Regno'], $row['Medrec'], $response['id']);
                return [
                    'status' => 'success',
                    'id' => $response['id']
                ];
            } else {
                try {
                    $bundle = $this->service->get('Encounter', ['subject' => $row['IHSSatuSehat']]);
                    $foundId = null;
                    if (isset($bundle['entry']) && is_array($bundle['entry'])) {
                        foreach ($bundle['entry'] as $entry) {
                            $res = $entry['resource'] ?? [];
                            if (isset($res['identifier']) && is_array($res['identifier'])) {
                                foreach ($res['identifier'] as $ident) {
                                    if (($ident['value'] ?? null) === ($row['Regno'] ?? null)) {
                                        $foundId = $res['id'] ?? null;
                                        break 2;
                                    }
                                }
                            }
                        }
                        if (!$foundId) {
                            $foundId = $bundle['entry'][0]['resource']['id'] ?? null;
                        }
                    }
                    if ($foundId) {
                        $model->updateEncounter($row['Regno'], $row['Medrec'], $foundId);
                        return [
                            'status' => 'success',
                            'id' => $foundId
                        ];
                    }
                    return [
                        'status' => 'failed',
                        'response' => $response,
                        'fallback' => $bundle ?? null
                    ];
                } catch (\Exception $e2) {
                    return [
                        'status' => 'error',
                        'message' => $e2->getMessage(),
                        'response' => $response
                    ];
                }
            }
        } catch (\Exception $e) {
            try {
                $bundle = $this->service->get('Encounter', ['subject' => $row['IHSSatuSehat']]);
                $foundId = null;
                if (isset($bundle['entry']) && is_array($bundle['entry'])) {
                    foreach ($bundle['entry'] as $entry) {
                        $res = $entry['resource'] ?? [];
                        if (isset($res['identifier']) && is_array($res['identifier'])) {
                            foreach ($res['identifier'] as $ident) {
                                if (($ident['value'] ?? null) === ($row['Regno'] ?? null)) {
                                    $foundId = $res['id'] ?? null;
                                    break 2;
                                }
                            }
                        }
                    }
                    if (!$foundId) {
                        $foundId = $bundle['entry'][0]['resource']['id'] ?? null;
                    }
                }
                if ($foundId) {
                    $model->updateEncounter($row['Regno'], $row['Medrec'], $foundId);
                    return [
                        'status' => 'success',
                        'id' => $foundId
                    ];
                }
                return [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'fallback' => $bundle ?? null
                ];
            } catch (\Exception $e3) {
                return [
                    'status' => 'error',
                    'message' => $e3->getMessage()
                ];
            }
        }
    }

    private function processRowConditions($row)
    {
        $encounterId = $row['EcounterSatuSehat'];
        if (empty($encounterId)) {
            return ['status' => 'skipped', 'message' => 'Belum memiliki Encounter ID'];
        }

        $results = [];

        $diagnosis = new EncounterDiagnosis($this->service);
        $res = $diagnosis->push($row, $encounterId);
        if ($res) {
            $results['diagnosis'] = $res;
        }

        $keluhanUtama = new KeluhanUtama($this->service);
        $res = $keluhanUtama->push($row, $encounterId);
        if ($res) {
            $results['keluhan_utama'] = $res;
        }

        $meninggalkanFaskes = new MeninggalkanFaskes($this->service);
        $res = $meninggalkanFaskes->push($row, $encounterId);
        if ($res) {
            $results['meninggalkan_faskes'] = $res;
        }

        // $riwayatPenyakitSekarang = new RiwayatPenyakitSekarang($this->service);
        // $res = $riwayatPenyakitSekarang->push($row, $encounterId);
        // if ($res) {
        //     $results['riwayat_penyakit_sekarang'] = $res;
        // }

        // $riwayatPenyakitTerdahulu = new RiwayatPenyakitTerdahulu($this->service);
        // $res = $riwayatPenyakitTerdahulu->push($row, $encounterId);
        // if ($res) {
        //     $results['riwayat_penyakit_terdahulu'] = $res;
        // }

        return $results;
    }

    private function processRowObservations($row)
    {
        $encounterId = $row['EcounterSatuSehat'];
        if (empty($encounterId)) {
            return ['status' => 'skipped', 'message' => 'Belum memiliki Encounter ID'];
        }

        $results = [];

        // Tanda Vital
        $obsClasses = [
            'td_sistolik' => new TDSistolik($this->service),
            'td_diastolik' => new TDDiastolik($this->service),
            'suhu_tubuh' => new SuhuTubuh($this->service),
            'denyut_jantung' => new DenyutJantung($this->service),
            'frekuensi_pernapasan' => new FrekuensiPernapasan($this->service),
            'saturasi_oksigen' => new SaturasiOksigen($this->service),
            'tinggi_badan' => new TinggiBadan($this->service),
            'berat_badan' => new BeratBadan($this->service),
            // 'tingkat_kesadaran' => new TingkatKesadaran($this->service),
            // 'kepala' => new Kepala($this->service),
            // 'mata' => new Mata($this->service),
            // 'telinga' => new Telinga($this->service),
            // 'luas_permukaan_tubuh' => new LuasPermukaanTubuh($this->service),
        ];

        foreach ($obsClasses as $key => $obs) {
            $res = $obs->push($row, $encounterId);
            if ($res) {
                $results[$key] = $res;
            }
        }

        return $results;
    }

    private function processRowClinicalImpression($row, $keluhanUtamaId = null)
    {
        $encounterId = $row['EcounterSatuSehat'];
        if (empty($encounterId)) {
            return ['status' => 'skipped', 'message' => 'Belum memiliki Encounter ID'];
        }

        $results = [];

        $clinicalImpression = new ClinicalImpression($this->service);
        $res = $clinicalImpression->push($row, $encounterId, $keluhanUtamaId);
        if ($res) {
            $results['clinical_impression'] = $res;
        }

        $riwayatPerjalananPenyakit = new RiwayatPerjalananPenyakit($this->service);
        $res = $riwayatPerjalananPenyakit->push($row, $encounterId);
        if ($res) {
            $results['riwayat_perjalanan_penyakit'] = $res;
        }

        return $results;
    }

    private function processRowComposition($row, $conditionId = null)
    {
        $encounterId = $row['EcounterSatuSehat'];
        if (empty($encounterId)) {
            return ['status' => 'skipped', 'message' => 'Belum memiliki Encounter ID'];
        }

        $results = [];

        $edukasiDiet = new EdukasiDiet($this->service);
        $res = $edukasiDiet->push($row, $encounterId, $conditionId);
        if ($res) {
            $results['edukasit_diet'] = $res;
        }

        return $results;
    }

    private function processRowEpisodeOfCare($row, $conditionId = null)
    {
        $encounterId = $row['EcounterSatuSehat'];
        if (empty($encounterId)) {
            return ['status' => 'skipped', 'message' => 'Belum memiliki Encounter ID'];
        }

        $results = [];

        $episodeOfCare = new EpisodeOfCare($this->service);
        $res = $episodeOfCare->push($row, $encounterId, $conditionId);
        if ($res) {
            $results['episode_of_care'] = $res;
        }

        return $results;
    }

    private function processRowGoals($row, $conditionId = null)
    {
        $encounterId = $row['EcounterSatuSehat'];
        if (empty($encounterId)) {
            return ['status' => 'skipped', 'message' => 'Belum memiliki Encounter ID'];
        }

        $results = [];

        $tujuanPerawatan = new TujuanPerawatan($this->service);
        $res = $tujuanPerawatan->push($row, $encounterId, $conditionId);
        if ($res) {
            $results['tujuan_perawatan'] = $res;
        }

        return $results;
    }

    private function processRowCarePlan($row, $goalId = null)
    {
        $encounterId = $row['EcounterSatuSehat'];
        if (empty($encounterId)) {
            return ['status' => 'skipped', 'message' => 'Belum memiliki Encounter ID'];
        }

        $results = [];

        $carePlan = new CarePlan($this->service);
        $res = $carePlan->push($row, $encounterId);
        if ($res) {
            $results['care_plan'] = $res;
        }

        $rencanaRawatJalanPasien = new RencanaRawatJalanPasien($this->service);
        $res = $rencanaRawatJalanPasien->push($row, $encounterId, $goalId);
        if ($res) {
            $results['rencana_rawat_jalan_pasien'] = $res;
        }

        $instruksiMedikDanKeperawatanPasien = new InstruksiMedikDanKeperawatanPasien($this->service);
        $res = $instruksiMedikDanKeperawatanPasien->push($row, $encounterId, $goalId);
        if ($res) {
            $results['instruksi_medik_dan_keperawatan_pasien'] = $res;
        }

        return $results;
    }

    // Helper method untuk internal call (Legacy support for single endpoint)
    private function processEncounter($date)
    {
        $model = new Register();
        $data = $model->getEncounterData($date);
        $results = [];

        foreach ($data as $row) {
            $results[$row['Regno']] = $this->sendEncounter($row, $model);
        }
        return $results;
    }

    // Helper method untuk internal call (Legacy support for single endpoint)
    private function processCondition($date)
    {
        $model = new Register();
        $data = $model->getEncounterData($date);
        $results = [];

        foreach ($data as $row) {
            $results[$row['Regno']] = $this->processRowConditions($row);
        }
        return $results;
    }

    // Helper method untuk internal call
    private function processObservation($date)
    {
        $model = new Register();
        $data = $model->getEncounterData($date);
        $results = [];

        foreach ($data as $row) {
            $results[$row['Regno']] = $this->processRowObservations($row);
        }
        return $results;
    }

    // Helper method untuk internal call
    private function processClinicalImpression($date)
    {
        $model = new Register();
        $data = $model->getEncounterData($date);
        $results = [];

        foreach ($data as $row) {
            $results[$row['Regno']] = $this->processRowClinicalImpression($row);
        }
        return $results;
    }

    // Helper method untuk internal call
    private function processGoal($date)
    {
        $model = new Register();
        $data = $model->getEncounterData($date);
        $results = [];

        foreach ($data as $row) {
            // Note: Standalone goal processing might lack condition context if not retrieved
            // Here we just pass null for conditionId or we'd need to fetch it
            $results[$row['Regno']] = $this->processRowGoals($row);
        }
        return $results;
    }

    public function postEncounter()
    {
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $results = $this->processEncounter($date);

        return $this->response->setJSON([
            'status' => true,
            'processed' => count($results),
            'data' => $results
        ]);
    }

    public function postCondition()
    {
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $results = $this->processCondition($date);

        return $this->response->setJSON([
            'status' => true,
            'processed' => count($results),
            'data' => $results
        ]);
    }

    public function postObservation()
    {
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $results = $this->processObservation($date);

        return $this->response->setJSON([
            'status' => true,
            'processed' => count($results),
            'data' => $results
        ]);
    }

    public function postClinicalImpression()
    {
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $results = $this->processClinicalImpression($date);

        return $this->response->setJSON([
            'status' => true,
            'processed' => count($results),
            'data' => $results
        ]);
    }

    public function postGoal()
    {
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $results = $this->processGoal($date);

        return $this->response->setJSON([
            'status' => true,
            'processed' => count($results),
            'data' => $results
        ]);
    }

    public function pushAll()
    {
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $model = new Register();
        $data = $model->getEncounterData($date);

        $logModel = new SatuSehatLogModel();
        $logsToInsert = [];

        $finalResults = [];

        foreach ($data as $row) {
            // Cek jika IHSSatuSehat kosong, coba cari by NIK
            if (empty($row['IHSSatuSehat']) && !empty($row['NoIden'])) {
                try {
                    $nik = $row['NoIden'];
                    // Identifier for NIK: https://fhir.kemkes.go.id/id/nik
                    $queryParams = ['identifier' => 'https://fhir.kemkes.go.id/id/nik|' . $nik];
                    $response = $this->service->get('Patient', $queryParams);

                    if (isset($response['total']) && $response['total'] > 0 && !empty($response['entry'][0]['resource']['id'])) {
                        $ihsId = $response['entry'][0]['resource']['id'];

                        // Update Database MasterPS
                        $masterPSModel = new \App\Models\MasterPS();
                        $masterPSModel->update($row['Medrec'], ['IHSSatuSehat' => $ihsId]);

                        // Update variable row untuk proses selanjutnya
                        $row['IHSSatuSehat'] = $ihsId;
                    }
                } catch (\Exception $e) {
                    // Ignore error, continue process (will likely fail or skip if IHS still missing)
                }
            }
            if ($row['KdPoli'] == '45') // POLI EDELWEIS / HIV
            {
                $row['SnomedCodeKeluhanUtama'] = '86406008';
                $row['SnomedDisplayKeluhanUtama'] = 'Human immunodeficiency virus infection';
            } else if ($row['KdPoli'] == '44') // POLI DOT / TBC
            {
                $row['SnomedCodeKeluhanUtama'] = '56717001';
                $row['SnomedDisplayKeluhanUtama'] = 'Tuberculosis';
            } else if ($row['KdPoli'] == '31') // POLI HEMODIALISA / CUCI DARAH
            {
                $row['SnomedCodeKeluhanUtama'] = '709044004';
                $row['SnomedDisplayKeluhanUtama'] = 'End stage renal disease';
            }

            $encounterRes = $this->sendEncounter($row, $model);

            if ($encounterRes['status'] === 'success' && isset($encounterRes['id'])) {
                $row['EcounterSatuSehat'] = $encounterRes['id'];
                $logsToInsert[] = [
                    'Regno' => $row['Regno'],
                    'resourceType' => 'Encounter',
                    'resourceSubType' => '',
                    'resourceID' => $encounterRes['id'],
                ];
            } else if (isset($encounterRes['id'])) {
                $logsToInsert[] = [
                    'Regno' => $row['Regno'],
                    'resourceType' => 'Encounter',
                    'resourceSubType' => '',
                    'resourceID' => $encounterRes['id'],
                ];
            }

            if (empty($row['EcounterSatuSehat'])) {
                $finalResults[] = [
                    'regno' => $row['Regno'],
                    'encounter' => $encounterRes,
                    'message' => 'Belum memiliki Encounter ID'
                ];
                continue;
            }

            $conditionRes = $this->processRowConditions($row);
            foreach ($conditionRes as $key => $res) {
                if (isset($res['id'])) {
                    $logsToInsert[] = [
                        'Regno' => $row['Regno'],
                        'resourceType' => 'Condition',
                        'resourceSubType' => $key,
                        'resourceID' => $res['id'],
                    ];
                }
            }

            // Extract Keluhan Utama Condition ID if available
            $keluhanUtamaId = null;
            if (
                isset($conditionRes['keluhan_utama']['status']) &&
                $conditionRes['keluhan_utama']['status'] === 'success' &&
                isset($conditionRes['keluhan_utama']['id'])
            ) {
                $keluhanUtamaId = $conditionRes['keluhan_utama']['id'];
            }

            $observationRes = $this->processRowObservations($row);
            foreach ($observationRes as $key => $res) {
                if (isset($res['id'])) {
                    $logsToInsert[] = [
                        'Regno' => $row['Regno'],
                        'resourceType' => 'Observation',
                        'resourceSubType' => $key,
                        'resourceID' => $res['id'],
                    ];
                }
            }

            $clinicalImpressionRes = $this->processRowClinicalImpression($row, $keluhanUtamaId);
            foreach ($clinicalImpressionRes as $key => $res) {
                if (isset($res['id'])) {
                    $logsToInsert[] = [
                        'Regno' => $row['Regno'],
                        'resourceType' => 'ClinicalImpression',
                        'resourceSubType' => $key,
                        'resourceID' => $res['id'],
                    ];
                }
            }

            $goalRes = $this->processRowGoals($row, $keluhanUtamaId);
            if (isset($goalRes['tujuan_perawatan']['id'])) {
                $logsToInsert[] = [
                    'Regno' => $row['Regno'],
                    'resourceType' => 'Goal',
                    'resourceSubType' => 'tujuan_perawatan',
                    'resourceID' => $goalRes['tujuan_perawatan']['id'],
                ];
            }

            $carePlanRes = [];
            if (
                isset($goalRes['tujuan_perawatan']['status']) &&
                $goalRes['tujuan_perawatan']['status'] === 'success' &&
                isset($goalRes['tujuan_perawatan']['id'])
            ) {
                $carePlanRes = $this->processRowCarePlan($row, $goalRes['tujuan_perawatan']['id']);
                foreach ($carePlanRes as $key => $res) {
                    if (isset($res['id'])) {
                        $logsToInsert[] = [
                            'Regno' => $row['Regno'],
                            'resourceType' => 'CarePlan',
                            'resourceSubType' => $key,
                            'resourceID' => $res['id'],
                        ];
                    }
                }
            }
            $compositionRes = $this->processRowComposition($row);
            foreach ($compositionRes as $key => $res) {
                if (isset($res['id'])) {
                    $logsToInsert[] = [
                        'Regno' => $row['Regno'],
                        'resourceType' => 'Composition',
                        'resourceSubType' => $key,
                        'resourceID' => $res['id'],
                    ];
                }
            }
            $procedureController = new Procedure($this->service);
            $procedureRes = $procedureController->push($row, $row['EcounterSatuSehat']);
            if (isset($procedureRes['id'])) {
                $logsToInsert[] = [
                    'Regno' => $row['Regno'],
                    'resourceType' => 'Procedure',
                    'resourceSubType' => 'procedure',
                    'resourceID' => $procedureRes['id'],
                ];
            }
            $episodeOfCareRes = [];
            if ($row['KdPoli'] == '45' || $row['KdPoli'] == '44' || $row['KdPoli'] == '31') // POLI EDELWEIS / HIV / DOT / TBC / HEMODIALISA / CUCI DARAH
            {
                $episodeOfCareController = new EpisodeOfCare($this->service);
                $episodeOfCareRes = $episodeOfCareController->push($row, $row['EcounterSatuSehat'], $keluhanUtamaId);
                if (isset($episodeOfCareRes['id'])) {
                    $logsToInsert[] = [
                        'Regno' => $row['Regno'],
                        'resourceType' => 'EpisodeOfCare',
                        'resourceSubType' => 'episode_of_care',
                        'resourceID' => $episodeOfCareRes['id'],
                    ];
                }
            }

            $allergyController = new AllergyIntolerance($this->service);
            $allergyRes = $allergyController->push($row, $row['EcounterSatuSehat']);
            if (isset($allergyRes['id'])) {
                $logsToInsert[] = [
                    'Regno' => $row['Regno'],
                    'resourceType' => 'AllergyIntolerance',
                    'resourceSubType' => 'allergy',
                    'resourceID' => $allergyRes['id'],
                ];
            }

            $medicationRes = $this->processRowMedication($row);
            foreach ($medicationRes as $medItem) {
                if (isset($medItem['medication']['id'])) {
                    $logsToInsert[] = [
                        'Regno' => $row['Regno'],
                        'resourceType' => 'Medication',
                        'resourceSubType' => 'medication',
                        'resourceID' => $medItem['medication']['id'],
                    ];
                }
                if (isset($medItem['medication_request']['id'])) {
                    $logsToInsert[] = [
                        'Regno' => $row['Regno'],
                        'resourceType' => 'MedicationRequest',
                        'resourceSubType' => 'medication_request',
                        'resourceID' => $medItem['medication_request']['id'],
                    ];
                }
                if (isset($medItem['medication_dispense']['id'])) {
                    $logsToInsert[] = [
                        'Regno' => $row['Regno'],
                        'resourceType' => 'MedicationDispense',
                        'resourceSubType' => 'medication_dispense',
                        'resourceID' => $medItem['medication_dispense']['id'],
                    ];
                }
            }

            $labRes = $this->processRowLab($row);
            if (isset($labRes['results'])) {
                foreach ($labRes['results'] as $labItem) {
                    if (isset($labItem['specimen']['id'])) {
                        $logsToInsert[] = [
                            'Regno' => $row['Regno'],
                            'resourceType' => 'Specimen',
                            'resourceSubType' => 'specimen',
                            'resourceID' => $labItem['specimen']['id'],
                        ];
                    }
                    if (!empty($labItem['observation_ids'])) {
                        foreach ($labItem['observation_ids'] as $obsId) {
                            $logsToInsert[] = [
                                'Regno' => $row['Regno'],
                                'resourceType' => 'Observation',
                                'resourceSubType' => 'laboratorium',
                                'resourceID' => $obsId,
                            ];
                        }
                    }
                }
            }
            if (isset($labRes['orders'])) {
                foreach ($labRes['orders'] as $srId) {
                    $logsToInsert[] = [
                        'Regno' => $row['Regno'],
                        'resourceType' => 'ServiceRequest',
                        'resourceSubType' => 'laboratorium',
                        'resourceID' => $srId,
                    ];
                }
            }

            $immunizationRes = $this->processRowImmunization($row);
            foreach ($immunizationRes as $immItem) {
                if (isset($immItem['result']['id'])) {
                    $logsToInsert[] = [
                        'Regno' => $row['Regno'],
                        'resourceType' => 'Immunization',
                        'resourceSubType' => $immItem['kode_obat'],
                        'resourceID' => $immItem['result']['id'],
                    ];
                }
            }

            $qrRes = $this->processRowQuestionnaireResponse($row);
            if (isset($qrRes['id'])) {
                $logsToInsert[] = [
                    'Regno' => $row['Regno'],
                    'resourceType' => 'QuestionnaireResponse',
                    'resourceSubType' => 'questionnaire_response',
                    'resourceID' => $qrRes['id'],
                ];
            }

            $medStatementRes = $this->processRowMedicationStatement($row);
            if (is_array($medStatementRes) && isset($medStatementRes['result']['id'])) {
                $logsToInsert[] = [
                    'Regno' => $row['Regno'],
                    'resourceType' => 'MedicationStatement',
                    'resourceSubType' => $medStatementRes['kode_obat'] ?? 'medication_statement',
                    'resourceID' => $medStatementRes['result']['id'],
                ];
            }

            $drController = new DiagnosticReport($this->service);
            $diagnosticReportRes = [];
            if (isset($labRes['results'])) {
                foreach ($labRes['results'] as $idx => $labItem) {
                    if (empty($labItem['dr_row']) || !is_array($labItem['dr_row'])) {
                        continue;
                    }

                    $drRes = $drController->push($labItem['dr_row'], $row['EcounterSatuSehat']);
                    $labRes['results'][$idx]['diagnostic_report'] = $drRes;
                    $diagnosticReportRes[] = $drRes;

                    if (isset($drRes['id'])) {
                        $logsToInsert[] = [
                            'Regno' => $row['Regno'],
                            'resourceType' => 'DiagnosticReport',
                            'resourceSubType' => 'diagnostic_report',
                            'resourceID' => $drRes['id'],
                        ];
                    }
                }
            }

            $finalResults[] = [
                'regno' => $row['Regno'],
                'encounter' => $encounterRes,
                'condition' => $conditionRes,
                'observation' => $observationRes,
                'procedure' => $procedureRes,
                'clinical_impression' => $clinicalImpressionRes,
                'goal' => $goalRes,
                'care_plan' => $carePlanRes,
                'composition' => $compositionRes,
                'episode_of_care' => $episodeOfCareRes,
                'allergy_intolerance' => $allergyRes,
                'immunization' => $immunizationRes,
                'questionnaire_response' => $qrRes,
                'medication' => $medicationRes,
                'medication_statement' => $medStatementRes,
                'diagnostic_report' => $diagnosticReportRes,
                'lab_results' => $labRes,
            ];
        }

        if (!empty($logsToInsert)) {
            $logModel->insertBatch($logsToInsert);
        }

        return $this->response->setJSON([
            'status' => true,
            'message' => 'Bulk push completed',
            'data' => $finalResults
        ]);
    }

    private function processRowMedication($row)
    {
        if (!isset($row['EcounterSatuSehat']) || empty($row['EcounterSatuSehat'])) {
            return ['status' => 'skipped', 'message' => 'No Encounter ID'];
        }

        $apotekModel = new ApotekModel();
        $results = [];
        $medicationController = new Medication($this->service);
        $medicationRequestController = new MedicationRequest($this->service);
        $medicationDispenseController = new MedicationDispense($this->service);
        $obats = $apotekModel->getObatByRegno($row['Regno']);
        if (empty($obats)) {
            $obats = $apotekModel->getDispenseObatByRegno($row['Regno']);
        }

        foreach ($obats as $index => $obat) {
            $obat['Urutan'] = $index + 1;

            $medRes = $medicationController->push($obat, $row['EcounterSatuSehat']);

            $medId = null;
            if (isset($medRes['id'])) {
                $medId = $medRes['id'];
            }

            if ($medId) {
                $reqData = array_merge($row, $obat);
                $reqData['MedicationId'] = $medId;

                $reqData['TglResep'] = $obat['TglResep'] ?? ($obat['RegDate'] ?? ($obat['Regdate'] ?? null));
                $reqData['AturanPakai'] = $obat['AturanPakai'] ?? null;

                $notes = [];
                if (!empty($obat['NoteCaraMinumObat'])) $notes[] = $obat['NoteCaraMinumObat'];
                if (!empty($obat['NoteSigna'])) $notes[] = $obat['NoteSigna'];
                $reqData['CatatanMinum'] = implode(', ', $notes);

                $reqData['InstruksiPasien'] = $obat['KeteranganPakai'] ?? null;
                $reqData['JumlahObat'] = $obat['Qty'] ?? null;
                $reqData['SatuanObat'] = $obat['Satuan'] ?? 'TAB';

                $reqRes = $medicationRequestController->push($reqData, $row['EcounterSatuSehat']);

                $medRequestId = null;
                if (isset($reqRes['id'])) {
                    $medRequestId = $reqRes['id'];
                }

                $dispenseRes = ['status' => 'skipped', 'message' => 'MedicationRequest failed'];
                if ($medRequestId) {
                    $reqData['MedicationRequestId'] = $medRequestId;
                    $dispenseRes = $medicationDispenseController->push($reqData, $row['EcounterSatuSehat'], $medRequestId);
                }

                $results[] = [
                    'medication' => $medRes,
                    'medication_request' => $reqRes,
                    'medication_dispense' => $dispenseRes
                ];
            } else {
                $results[] = [
                    'medication' => $medRes,
                    'error' => 'Failed to get Medication ID'
                ];
            }
        }

        return $results;
    }

    private function processRowLab($row)
    {
        if (!isset($row['EcounterSatuSehat']) || empty($row['EcounterSatuSehat'])) {
            return ['status' => 'skipped', 'message' => 'No Encounter ID'];
        }

        $labModel = new LaboratoriumModel();

        // 1. Process Orders (ServiceRequest) from HeadBilLabTEMP
        $orders = $labModel->getLabOrders($row['Regno']);
        $serviceRequestResults = [];
        $serviceRequestResponses = [];
        $srController = new LaboratoriumServiceRequest($this->service);

        foreach ($orders as $order) {
            // Merge row data (Patient info) with order data
            $orderData = array_merge($row, $order);
            // push returns ['status' => 'success', 'id' => '...']
            $res = $srController->push($orderData, $row['EcounterSatuSehat']);
            $serviceRequestResponses[$order['NoTran']] = $res;

            if (isset($res['id'])) {
                // Store mapped ServiceRequest ID for Results linking
                $serviceRequestResults[$order['NoTran']] = $res['id'];
            }
        }

        // 2. Process Results (DiagnosticReport, Specimen, Observation) from HeadBilLab + DetailBilLab
        $results = $labModel->getLabResults($row['Regno']);

        // Group results by Header NoTran
        $groupedResults = [];
        foreach ($results as $res) {
            $groupedResults[$res['HeaderNoTran']][] = $res;
        }

        $labResults = [];
        $statusPuasaController = new StatusPuasa($this->service);
        $specimenController = new LaboratoriumSpecimen($this->service);
        $obsController = new LaboratoriumObservation($this->service);

        foreach ($groupedResults as $noTran => $details) {
            if (empty($details)) continue;

            // Use first detail for header info
            $headerData = array_merge($row, $details[0]);

            // Link ServiceRequest ID if available
            $serviceRequestId = $serviceRequestResults[$noTran] ?? null;
            $headerData['ServiceRequestId'] = $serviceRequestId;

            $srFallbackRes = null;
            if (empty($serviceRequestId)) {
                $srFallbackRes = $srController->push($headerData, $row['EcounterSatuSehat']);
                if (isset($srFallbackRes['id'])) {
                    $serviceRequestId = $srFallbackRes['id'];
                    $headerData['ServiceRequestId'] = $serviceRequestId;
                }
            }

            // 1. Send Status Puasa
            $statusPuasaRes = $statusPuasaController->push($headerData, $row['EcounterSatuSehat']);
            $statusPuasaId = $statusPuasaRes['id'] ?? null;
            $headerData['StatusPuasaId'] = $statusPuasaId;

            // 2.1 Send Specimen
            $specimenRes = $specimenController->push($headerData, $row['EcounterSatuSehat']);
            $specimenId = $specimenRes['id'] ?? null;
            $headerData['SpecimenId'] = $specimenId;

            // 2.2 Send Observations (Details)
            $observationIds = [];
            foreach ($details as $detail) {
                $detailData = array_merge($headerData, $detail);
                $detailData['SpecimenId'] = $specimenId;
                $detailData['ServiceRequestId'] = $serviceRequestId;

                $obsRes = $obsController->push($detailData, $row['EcounterSatuSehat']);
                if (isset($obsRes['id'])) {
                    $observationIds[] = $obsRes['id'];
                }
            }

            $drRow = $headerData;
            $drRow['NoTran'] = $noTran;
            $drRow['ObservationIds'] = $observationIds;

            $labResults[] = [
                'no_tran' => $noTran,
                'specimen' => $specimenRes,
                'status_puasa' => $statusPuasaRes,
                'observation_ids' => $observationIds,
                'observations_count' => count($observationIds),
                'dr_row' => $drRow,
                'service_request' => $serviceRequestResponses[$noTran] ?? $srFallbackRes
            ];
        }

        return [
            'orders' => $serviceRequestResults,
            'results' => $labResults,
            'orders_responses' => $serviceRequestResponses
        ];
    }

    private function processRowImmunization($row)
    {
        if (!isset($row['EcounterSatuSehat']) || empty($row['EcounterSatuSehat'])) {
            return ['status' => 'skipped', 'message' => 'No Encounter ID'];
        }

        // Mapping Local KodeObat to KFA/System
        // Format: 'LocalCode' => ['KFA_Code', 'Display']
        $vaccineMap = [
            '0051121' => ['VG16', 'VAKSIN CORONAVAC SINGLE DOSE'], // CoronaVac
            '010323' => ['VG105', 'VAKSIN PNEUMOVAX 23 INJ'], // Pneumococcal
            '01090525' => ['VG55', 'VAKSIN PREVENAR/ PCV DINKES'], // PCV
            '01120225' => ['VG123', 'VAKSIN PREVENAR 20 INJ'], // PCV 20 ?
            '01200625' => ['VG42', 'VAKSIN IPV POLIO INJ'], // IPV
            '01200824' => ['VG33', 'VAKSIN INFLUVAC TETRA SH'], // Influenza
            '01250723' => ['VG117', 'VAKSIN VARIVAX'], // Varicella
            '0170616' => ['VG29', 'VAKSIN INFANRIX HEXA'], // DTaP-IPV-HepB-Hib
            '030123' => ['VG120', 'VAKSIN MENIVAX ACYW'], // Meningococcal
            '03231024' => ['VG124', 'VAKSIN INLIVE'], // Japanese Encephalitis ?
            '050618' => ['VG51', 'VAKSIN MR (MEASLES AND RUBELLA) DINKES'], // MR
            '050620' => ['VG50', 'VAKSIN MMR II'], // MMR
            '05161123' => ['VG13', 'VAKSIN BOOSTRIX 0,5 ML PFS'], // Tdap
            '070415' => ['VG30', 'VAKSIN HAVRIX 720 JUNIOR INJ'], // Hepatitis A
            '071217' => ['VG33', 'VAKSIN FLUQUADRI 0,25 ML'], // Influenza
            '080415' => ['VG45', 'VAKSIN HEPATITIS B REKOMBINAN 1ML'], // HepB
            '081216' => ['VG116', 'VAKSIN VARILRIX'], // Varicella
            '11102402' => ['VG46', 'VAKSIN ENGERIX B 0.5'], // HepB
            '150518' => ['VG122', 'VAKSIN ROTATEQ'], // Rotavirus
            '150719' => ['VG10', 'VAKSIN B.C.G DINKES'], // BCG
            '150818' => ['VG117', 'VAKSIN VARICELLA'], // Varicella
            '150921' => ['VG16', 'VAKSIN CORONAVAC 2 DOSIS'], // CoronaVac
            '160321' => ['VG17', 'VAKSIN COVID 19 (6 DOSIS)/PFIZER'], // Pfizer
            '160623' => ['VG125', 'VAKSIN QDENGA 1 POWDER+1 SYR 0,5 ML+2 NDLS'], // Dengue
            '180122' => ['VG107', 'VAKSIN PENTABIO DINKES'], // DTP-HB-Hib
            '210722' => ['VG26', 'VAKSIN TETRAXIM'], // DTaP-IPV
            '240218' => ['VG45', 'VAKSIN HEPATITIS B 0,5 ML NEO (DINKES)'], // HepB
            '240522' => ['VG126', 'VAKSIN IMOJEV INJ'], // JE
            '24102014' => ['VG107', 'VAKSIN PENTABIO 0,5 ML'], // DTP-HB-Hib
            '260124' => ['VG29', 'VAKSIN HEXAXIM INHEALTH'], // DTaP-IPV-HepB-Hib
            '27082016' => ['VG29', 'VAKSIN HEXAXIM'], // DTaP-IPV-HepB-Hib
            '271117' => ['VG51', 'VAKSIN MR (MEASLES AND RUBELLA)'], // MR
            '300616' => ['VG33', 'VAKSIN FLU BIO 0,5 ML'], // Influenza
            'AAM0908' => ['VG33', 'VAKSIN FLUARIX TETRA 0.5 ML'], // Influenza
            'AAM403' => ['VG122', 'VAKSIN ROTARIX'], // Rotavirus
            'AAM982' => ['VG55', 'VAKSIN SYNFLORIX'], // PCV
            'APL01' => ['VG102', 'VAKSIN THYPIM'], // Typhoid
            'APL126' => ['VG36', 'VAKSIN GARDASIL INJ 0,5 ML'], // HPV
            'APL217' => ['VG33', 'VAKSIN VAXIGRIP TETRA INJ 0,5 ML'], // Influenza
            'APL2823' => ['VG36', 'VAKSIN GARDASIL 9 INJ'], // HPV
            'KUF973' => ['VG83', 'VAKSIN POLIO (SABIN) TRIVALEN & PIPET'], // OPV
            'PNPRVNR' => ['VG55', 'VAKSIN PREVENAR INJ'], // PCV
            'TMC000' => ['VG10', 'VAKSIN B.C.G'], // BCG
            'TMC05' => ['VG45', 'VAKSIN HEPATITIS B RECOMBINEN 0,5 ML NEO'], // HepB
            'TMC08' => ['VG83', 'VAKSIN POLIO DINKES'], // OPV
        ];

        $apotekModel = new ApotekModel();
        $items = $apotekModel->getDispenseObatByRegno($row['Regno']);
        if (empty($items)) {
            $items = $apotekModel->getObatByRegno($row['Regno']);
        }

        $results = [];
        $immController = new ImmunizationPush($this->service);

        foreach ($items as $item) {
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

                $res = $immController->push($immData, $row['EcounterSatuSehat']);
                $results[] = [
                    'kode_obat' => $kodeObat,
                    'result' => $res
                ];
            }
        }

        return $results;
    }

    private function processRowQuestionnaireResponse($row)
    {
        if (!isset($row['EcounterSatuSehat']) || empty($row['EcounterSatuSehat'])) {
            return ['status' => 'skipped', 'message' => 'No Encounter ID'];
        }

        // Only send if there is data indicating a questionnaire response
        // Currently we use default logic in QuestionnaireResponse controller which sends "Keluarga Pra Sejahtera" if not specified.
        // Or we can check if certain fields exist.
        // Since user asked to "send it also", we will try to send it.

        $qrController = new QuestionnaireResponse($this->service);
        return $qrController->push($row, $row['EcounterSatuSehat']);
    }

    private function processRowMedicationStatement($row)
    {
        if (!isset($row['EcounterSatuSehat']) || empty($row['EcounterSatuSehat'])) {
            return ['status' => 'skipped', 'message' => 'No Encounter ID'];
        }

        $apotekModel = new ApotekModel();
        $items = $apotekModel->getDispenseObatByRegno($row['Regno']);
        if (empty($items)) {
            $items = $apotekModel->getObatByRegno($row['Regno']);
        }

        $msController = new MedicationStatement($this->service);

        $candidates = [];
        foreach ($items as $item) {
            if (!empty($item['KFA'])) {
                $candidates[] = $item;
            }
        }

        if (empty($candidates)) {
            if (empty($items)) {
                return ['status' => 'skipped', 'message' => 'Tidak ada data obat'];
            }
            return ['status' => 'skipped', 'message' => 'Tidak ada obat dengan KFA di MasterObat'];
        }

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
        $res = $msController->push($msData, $row['EcounterSatuSehat']);

        return [
            'kode_obat' => $picked['KodeObat'] ?? null,
            'nama_obat' => $picked['NamaObat'] ?? null,
            'kfa' => $picked['KFA'] ?? null,
            'result' => $res,
        ];
    }
}
