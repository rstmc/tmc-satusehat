<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\RadiologiOracleModel;
use App\Models\SatuSehatLogModel;
use App\Services\SatusehatService;
use App\Controllers\Api\SatuSehat\Procedure\Radiologi\StatusPuasa;
use App\Controllers\Api\SatuSehat\ServiceRequest\Radiologi\ServiceRequest as RadiologiServiceRequest;
use App\Controllers\Api\SatuSehat\DiagnosticReport\Radiologi\DiagnosticReport as RadiologiDiagnosticReport;

class RadiologiOracle extends BaseController
{
    protected $db;
    protected $builder;

    public function __construct()
    {
        $this->db      = \Config\Database::connect('oracle');
        $this->builder = $this->db->table('RAD_DATA');
    }

        // ==================== LIST DATA ====================
    public function listData()
    {
        try {
            // Ambil semua data dari tabel
            $data = $this->builder->get()->getResult();

            if (empty($data)) {
                return $this->response->setStatusCode(404)->setJSON([
                    'status'  => false,
                    'message' => 'Data tidak ditemukan'
                ]);
            }

            return $this->response->setStatusCode(200)->setJSON([
                'status' => true,
                'message' => 'Data ditemukan',
                'data' => $data
            ]);

        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => false,
                'message' => 'Gagal mengambil data',
                'error'   => $e->getMessage()
            ]);
        }
    }

    // ==================== SEARCH BERDASARKAN REGNO ====================
public function searchByRegno()
{
    $data = $this->request->getJSON(true);

    if (empty($data['REGNO'])) {
        return $this->response->setStatusCode(400)->setJSON([
            'status'  => false,
            'message' => 'REGNO wajib diisi'
        ]);
    }

    if (empty($data['NOTRAN'])) {
        return $this->response->setStatusCode(400)->setJSON([
            'status'  => false,
            'message' => 'NOTRAN wajib diisi'
        ]);
    }

    try {
        $result = $this->builder
            ->where('REGNO', $data['REGNO'])
            ->where('NOTRAN', $data['NOTRAN'])
            ->get()
            ->getResult();

        if (empty($result)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => false,
                'message' => 'Data tidak ditemukan'
            ]);
        }

        return $this->response->setJSON([
            'status' => true,
            'message' => 'Data ditemukan',
            'data' => $result
        ]);

    } catch (\Throwable $e) {
        return $this->response->setStatusCode(500)->setJSON([
            'status' => false,
            'message' => 'Gagal search data',
            'error' => $e->getMessage()
        ]);
    }
}


    // ==================== INSERT ====================
    public function insert()
    {
        $data = $this->request->getJSON(true);

        if (!$data) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => false,
                'message' => 'Invalid JSON payload'
            ]);
        }

        try {
            $this->builder->set('REGNO',  $data['REGNO'] ?? null);
            $this->builder->set('NOTRAN', $data['NOTRAN'] ?? null);

            if (!empty($data['REGDATE'])) {
                $this->builder->set(
                    'REGDATE',
                    "TO_DATE('{$data['REGDATE']}', 'YYYY-MM-DD HH24:MI:SS')",
                    false
                );
            }

            
            $service = new SatusehatService();
            $procedureController = new StatusPuasa($service);

            $encounterId = $data['ECOUNTERSATUSEHAT'] ?? null;
            $regdateStr = $data['REGDATE'] ?? '';
            $ts = $regdateStr ? strtotime($regdateStr) : false;
            $dateOnly = $ts ? date('Y-m-d', $ts) : date('Y-m-d');
            $timeOnly = $ts ? date('H:i:s', $ts) : '00:00:00';

            $row = [
                'IHSSatuSehat'   => $data['IHSSATUSEHAT'] ?? null,
                'KdDocSatuSehat' => $data['KdDPJP'] ?? null,
                'NmDoc' => $data['NmDPJP'] ?? null,
                'NmDocRad'          => $data['NMDOC'] ?? null,
                'kdDocSatuSehatRad'          => $data['kdDocSatuSehat'] ?? null,
                'kdIcd'          => $data['KDICD'] ?? null,
                'NmIcd'          => $data['DIAGNOSA'] ?? null,
                'Firstname'      => $data['FIRSTNAME'] ?? null,
                'Regdate'        => $dateOnly,
                'RegTime'        => $timeOnly,
            ];

                    
        $pushRes = $procedureController->push($row, $encounterId);
        if (!is_array($pushRes) || ($pushRes['status'] ?? '') !== 'success') {
            return $this->response->setJSON([
                'status'  => false,
                'message' => 'Gagal push FHIR Procedure Status Puasa',
                'detail'  => $pushRes
            ])->setStatusCode(502);
        }

        $statusPuasaId = $pushRes['id'] ?? null;

        $srController = new RadiologiServiceRequest($service);
        $row['StatusPuasaId'] = $statusPuasaId;
        $srRes = $srController->push($row, $encounterId);
        if (!is_array($srRes) || ($srRes['status'] ?? '') !== 'success') {
            return $this->response->setJSON([
                'status'  => false,
                'message' => 'Gagal push FHIR ServiceRequest Radiologi',
                'detail'  => $srRes
            ])->setStatusCode(502);
        }

        $drController = new RadiologiDiagnosticReport($service);
        $drRow = $row;
        $drRow['ServiceRequestId'] = $srRes['id'] ?? null;
        $drRow['NoTran'] = $data['NOTRAN'] ?? ($data['ACSN'] ?? uniqid());
        $drRow['Kesan'] = $data['HASIL'] ?? '';
        if (!empty($data['ImagingStudy_id'])) {
            $drRow['ImagingStudyId'] = $data['ImagingStudy_id'];
        } elseif (!empty($data['ImagingStudyId'])) {
            $drRow['ImagingStudyId'] = $data['ImagingStudyId'];
        }
        if (!empty($data['Observation_Rad'])) {
            $drRow['Observation_Rad'] = $data['Observation_Rad'];
        }
        $drRes = $drController->push($drRow, $encounterId);
        if (!is_array($drRes) || ($drRes['status'] ?? '') !== 'success') {
            return $this->response->setJSON([
                'status'  => false,
                'message' => 'Gagal push FHIR DiagnosticReport Radiologi',
                'detail'  => $drRes
            ])->setStatusCode(502);
        }

        $regno = $data['REGNO'] ?? null;
        $logModel = new SatuSehatLogModel();
        $logs = [];
        if ($statusPuasaId) {
            $logs[] = [
                'Regno' => $regno,
                'resourceType' => 'Procedure',
                'resourceSubType' => 'radiologi_status_puasa',
                'resourceID' => $statusPuasaId,
            ];
        }
        if (isset($srRes['id'])) {
            $logs[] = [
                'Regno' => $regno,
                'resourceType' => 'ServiceRequest',
                'resourceSubType' => 'radiologi',
                'resourceID' => $srRes['id'],
            ];
        }
        if (isset($drRes['id'])) {
            $logs[] = [
                'Regno' => $regno,
                'resourceType' => 'DiagnosticReport',
                'resourceSubType' => 'radiologi',
                'resourceID' => $drRes['id'],
            ];
        }
        if (!empty($logs)) {
            $logModel->insertBatch($logs);
        }

        $this->builder->set('MEDREC',       $data['MEDREC'] ?? null);
        $this->builder->set('FIRSTNAME',    $data['FIRSTNAME'] ?? null);
        $this->builder->set('IHSSATUSEHAT', $data['IHSSATUSEHAT'] ?? null);
        $this->builder->set('KDDOC',        $data['KDDOC'] ?? null);
        $this->builder->set('KDDOCSATUSEHAT', $data['KDDOCSATUSEHAT'] ?? null);
        $this->builder->set('NMDOC',        $data['NMDOC'] ?? null);
        $this->builder->set('ECOUNTERSATUSEHAT', $data['ECOUNTERSATUSEHAT'] ?? null);
        $this->builder->set('KDDPJP',       $data['KDDPJP'] ?? null);
        $this->builder->set('NMDPJP',       $data['NMDPJP'] ?? null);
        $this->builder->set('KDICD',        $data['KDICD'] ?? null);
        $this->builder->set('DIAGNOSA',     $data['DIAGNOSA'] ?? null);
        $this->builder->set('ASCN',         $data['ASCN'] ?? null);
        $this->builder->set('HASIL',        $data['HASIL'] ?? null);
        
        $builder->insert();

        return $this->response->setJSON([
            'status'  => true,
            'message' => 'Insert radiologi Oracle berhasil',
            'serviceRequestId' => $srRes['id'] ?? null,
            'diagnosticReportId' => $drRes['id'] ?? null,
            'statusPuasaId' => $statusPuasaId,
        ])->setStatusCode(201);

        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => false,
                'message' => 'Insert radiologi gagal',
                'error'   => $e->getMessage()
            ]);
        }
    }

    // ==================== UPDATE ====================
    public function update()
    {
        $data = $this->request->getJSON(true);

        if (empty($data['IDRAD'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => false,
                'message' => 'IDRAD wajib diisi untuk update'
            ]);
        }

        try {
            $this->builder->where('IDRAD', $data['IDRAD']);

            if (!empty($data['Regdate'])) {
                $this->builder->set(
                    'REGDATE',
                    "TO_DATE('{$data['Regdate']}', 'YYYY-MM-DD HH24:MI:SS')",
                    false
                );
            }
            
            $service = new SatusehatService();
            $procedureController = new StatusPuasa($service);

            $encounterId = $data['ECOUNTERSATUSEHAT'] ?? null;
            $regdateStr = $data['REGDATE'] ?? '';
            $ts = $regdateStr ? strtotime($regdateStr) : false;
            $dateOnly = $ts ? date('Y-m-d', $ts) : date('Y-m-d');
            $timeOnly = $ts ? date('H:i:s', $ts) : '00:00:00';

            $row = [
                'IHSSatuSehat'   => $data['IHSSATUSEHAT'] ?? null,
                'KdDocSatuSehat' => $data['KDDPJP'] ?? null,
                'NmDoc' => $data['NMDPJP'] ?? null,
                'NmDocRad'          => $data['NMDOC'] ?? null,
                'kdDocSatuSehatRad'          => $data['KDDOCSATUSEHAT'] ?? null,
                'kdIcd'          => $data['KDICD'] ?? null,
                'NmIcd'          => $data['DIAGNOSA'] ?? null,
                'Firstname'      => $data['FIRSTNAME'] ?? null,
                'Regdate'      => $data['REGDATE'] ?? null,
                'ACSN'      => $data['ASCN'] ?? null,
                'NOTRAN'      => $data['NOTRAN'] ?? null,
            ];
            $pushRes = $procedureController->push($row, $encounterId);
            if (!is_array($pushRes) || ($pushRes['status'] ?? '') !== 'success') {
                return $this->response->setJSON([
                    'status'  => false,
                    'message' => 'Gagal push FHIR Procedure Status Puasa',
                    'detail'  => $pushRes
                ])->setStatusCode(502);
            }

            $statusPuasaId = $pushRes['id'] ?? null;

            $srController = new RadiologiServiceRequest($service);
            $row['StatusPuasaId'] = $statusPuasaId;
            $srRes = $srController->push($row, $encounterId);
            if (!is_array($srRes) || ($srRes['status'] ?? '') !== 'success') {
                return $this->response->setJSON([
                    'status'  => false,
                    'message' => 'Gagal push FHIR ServiceRequest Radiologi',
                    'detail'  => $srRes
                ])->setStatusCode(502);
            }

            $drController = new RadiologiDiagnosticReport($service);
            $drRow = $row;
            $drRow['ServiceRequestId'] = $srRes['id'] ?? null;
            $drRow['NoTran'] = $data['NOTRAN'] ?? ($data['ACSN'] ?? uniqid());
            $drRow['Kesan'] = $data['HASIL'] ?? '';
            if (!empty($data['ImagingStudy_id'])) {
                $drRow['ImagingStudyId'] = $data['ImagingStudy_id'];
            } elseif (!empty($data['ImagingStudyId'])) {
                $drRow['ImagingStudyId'] = $data['ImagingStudyId'];
            }
            if (!empty($data['Observation_Rad'])) {
                $drRow['Observation_Rad'] = $data['Observation_Rad'];
            }
            $drRes = $drController->push($drRow, $encounterId);
            if (!is_array($drRes) || ($drRes['status'] ?? '') !== 'success') {
                return $this->response->setJSON([
                    'status'  => false,
                    'message' => 'Gagal push FHIR DiagnosticReport Radiologi',
                    'detail'  => $drRes
                ])->setStatusCode(502);
            }

            $regno = $data['REGNO'] ?? null;
            $logModel = new SatuSehatLogModel();
            $logs = [];
            if ($statusPuasaId) {
                $logs[] = [
                    'Regno' => $regno,
                    'resourceType' => 'Procedure',
                    'resourceSubType' => 'radiologi_status_puasa',
                    'resourceID' => $statusPuasaId,
                ];
            }
            if (isset($srRes['id'])) {
                $logs[] = [
                    'Regno' => $regno,
                    'resourceType' => 'ServiceRequest',
                    'resourceSubType' => 'radiologi',
                    'resourceID' => $srRes['id'],
                ];
            }
            if (isset($drRes['id'])) {
                $logs[] = [
                    'Regno' => $regno,
                    'resourceType' => 'DiagnosticReport',
                    'resourceSubType' => 'radiologi',
                    'resourceID' => $drRes['id'],
                ];
            }
            if (!empty($logs)) {
                $logModel->insertBatch($logs);
            }


            foreach ([
                'REGNO','NOTRAN','MEDREC','FIRSTNAME','IHSSATUSEHAT',
                'KDDOC','KDDOCSATUSEHAT','NMDOC','ECOUNTERSATUSEHAT',
                'KDDPJP','NMDPJP','KDICD','DIAGNOSA','ASCN','HASIL'
            ] as $field) {
                if (isset($data[$field])) {
                    $this->builder->set($field, $data[$field]);
                }
            }

            $this->builder->update();

        return $this->response->setJSON([
                'status'  => true,
                'message' => 'Update radiologi berhasil',
                'serviceRequestId' => $srRes ?? null,
                'diagnosticReportId' => $drRes ?? null,
                'statusPuasaId' => $statusPuasaId,
            ])->setStatusCode(200);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => false,
                'message' => 'Update radiologi gagal',
                'error'   => $e->getMessage()
            ]);
        }
    }

    // ==================== DELETE ====================
    public function delete()
    {
        $data = $this->request->getJSON(true);

        if (empty($data['IDRAD'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => false,
                'message' => 'IDRAD wajib diisi untuk delete'
            ]);
        }

        try {
            $this->builder->where('IDRAD', $data['IDRAD'])->delete();

            return $this->response->setStatusCode(200)->setJSON([
                'status'  => true,
                'message' => 'Delete radiologi berhasil'
            ]);

        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => false,
                'message' => 'Delete radiologi gagal',
                'error'   => $e->getMessage()
            ]);
        }
    }
}
