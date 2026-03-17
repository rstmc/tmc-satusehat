<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\RadiologiOracleModel;
use App\Models\SatuSehatLogModel;
use App\Services\SatusehatService;
use App\Controllers\Api\SatuSehat\Procedure\Radiologi\StatusPuasa;
use App\Controllers\Api\SatuSehat\ServiceRequest\Radiologi\ServiceRequest as RadiologiServiceRequest;
use App\Controllers\Api\SatuSehat\DiagnosticReport\Radiologi\DiagnosticReport as RadiologiDiagnosticReport;

class RadiologiInsert extends BaseController
{
public function index()
{
    $db = \Config\Database::connect('oracle');

    $data = $this->request->getJSON(true);

    if (!$data) {
        return $this->response->setJSON([
            'status'  => false,
            'message' => 'Invalid JSON payload'
        ])->setStatusCode(400);
    }

    try {
        $builder = $db->table('RAD_DATA');

        $builder->set('REGNO',  $data['REGNO'] ?? null);
        $builder->set('NOTRAN', $data['NOTRAN'] ?? null);

        // 🔥 INI KUNCI ORACLE DATE
        if (!empty($data['REGDATE'])) {
            $builder->set(
                'REGDATE',
                "TO_DATE('{$data['REGDATE']}', 'YYYY-MM-DD HH24:MI:SS')",
                false // <=== JANGAN DI-ESCAPE
            );
        }

        $builder->set('MEDREC',       $data['MEDREC'] ?? null);
        $builder->set('FIRSTNAME',    $data['FIRSTNAME'] ?? null);
        $builder->set('IHSSATUSEHAT', $data['IHSSATUSEHAT'] ?? null);
        $builder->set('KDDOC',        $data['KDDOC'] ?? null);
        $builder->set('KDDOCSATUSEHAT', $data['KDDOCSATUSEHAT'] ?? null);
        $builder->set('NMDOC',        $data['NMDOC'] ?? null);
        $builder->set('ECOUNTERSATUSEHAT', $data['ECOUNTERSATUSEHAT'] ?? null);
        $builder->set('KDDPJP',       $data['KDDPJP'] ?? null);
        $builder->set('NMDPJP',       $data['NMDPJP'] ?? null);
        $builder->set('KDICD',        $data['KDICD'] ?? null);
        $builder->set('DIAGNOSA',     $data['DIAGNOSA'] ?? null);
        $builder->set('ASCN',         $data['ASCN'] ?? null);
        $builder->set('HASIL',         $data['HASIL'] ?? null);

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

        $builder->insert();

        return $this->response->setJSON([
            'status'  => true,
            'message' => 'Insert radiologi Oracle berhasil',
            'serviceRequestId' => $srRes['id'] ?? null,
            'diagnosticReportId' => $drRes['id'] ?? null,
            'statusPuasaId' => $statusPuasaId,
        ])->setStatusCode(201);

    } catch (\Throwable $e) {
        return $this->response->setJSON([
            'status'  => false,
            'message' => 'Insert radiologi Oracle gagal',
            'error'   => $e->getMessage()
        ])->setStatusCode(500);
    }
}

}
