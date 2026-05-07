<?php

namespace App\Controllers\Api\SatuSehat;

use App\Controllers\BaseController;
use App\Services\SatusehatService;
use App\Controllers\Api\SatuSehat\Procedure\Radiologi\StatusPuasa;
use App\Controllers\Api\SatuSehat\Observation\Radiologi\PregnancyStatus;
use App\Controllers\Api\SatuSehat\AllergyIntolerance\Radiologi\BariumSulfateAllergy;
use App\Controllers\Api\SatuSehat\ServiceRequest\Radiologi\ServiceRequest;
use App\Controllers\Api\SatuSehat\ImagingStudy\ImagingStudy;
use App\Controllers\Api\SatuSehat\Observation\Radiologi\RadiologiObservation;
use App\Controllers\Api\SatuSehat\DiagnosticReport\Radiologi\DiagnosticReport;

class RadiologiIntegration extends BaseController
{
    protected $service;

    public function __construct()
    {
        $this->service = new SatusehatService();
    }

    public function pushProcedure()
    {
        $data = $this->request->getJSON(true);
        $encounterId = $data['encounter_id'] ?? null;
        $controller = new StatusPuasa($this->service);
        $res = $controller->push($data, $encounterId);
        return $this->response->setJSON($res);
    }

    public function pushPregnancyStatus()
    {
        $data = $this->request->getJSON(true);
        $encounterId = $data['encounter_id'] ?? null;
        $controller = new PregnancyStatus();
        $res = $controller->push($data, $encounterId);
        return $this->response->setJSON($res);
    }

    public function pushAllergy()
    {
        $data = $this->request->getJSON(true);
        $encounterId = $data['encounter_id'] ?? null;
        $controller = new BariumSulfateAllergy();
        $res = $controller->push($data, $encounterId);
        return $this->response->setJSON($res);
    }

    public function pushServiceRequest()
    {
        $data = $this->request->getJSON(true);
        $encounterId = $data['encounter_id'] ?? null;
        $controller = new ServiceRequest($this->service);
        $res = $controller->push($data, $encounterId);
        return $this->response->setJSON($res);
    }

    public function searchImagingStudy()
    {
        $acsn = $this->request->getGet('acsn');
        $controller = new ImagingStudy();
        $res = $controller->searchByAcsn($acsn);
        return $this->response->setJSON($res);
    }

    public function pushObservationResult()
    {
        $data = $this->request->getJSON(true);
        $encounterId = $data['encounter_id'] ?? null;
        $controller = new RadiologiObservation();
        $res = $controller->push($data, $encounterId);
        return $this->response->setJSON($res);
    }

    public function pushDiagnosticReport()
    {
        $data = $this->request->getJSON(true);
        $encounterId = $data['encounter_id'] ?? null;
        $controller = new DiagnosticReport();
        $res = $controller->push($data, $encounterId);
        return $this->response->setJSON($res);
    }

    public function pushRadiologyComplete()
    {
        $regno = $this->request->getPost('regno');
        $notran = $this->request->getPost('notran');

        if (!$regno || !$notran) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Regno and NoTran are required.'
            ])->setStatusCode(400);
        }

        $model = new \App\Models\RadiologiSqlsrvModel();
        $row = $model->getByRegnoNotran($regno, $notran);

        if (!$row) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Data radiology tidak ditemukan atau mapping belum lengkap.'
            ])->setStatusCode(404);
        }

        // Map field names for sub-controllers
        $row['KDDETAIL'] = $row['KDDetail'] ?? '';
        $row['NmTindakan'] = $row['NmTarif'];

        // Use modality from POST if provided, otherwise fallback to database value
        $postModality = $this->request->getPost('modality');
        if ($postModality) {
            $row['Modality'] = $postModality;
        }
        
        $encounterId = $row['EcounterSatuSehat'];
        $results = [];

        // 1. ServiceRequest
        $srController = new ServiceRequest($this->service);
        $srRes = $srController->push($row, $encounterId);
        $results['service_request'] = $srRes;

        if (isset($srRes['id'])) {
            $row['ServiceRequestId'] = $srRes['id'];
        }

        // 2. Observation (Result)
        $obsController = new RadiologiObservation();
        $obsRes = $obsController->push($row, $encounterId);
        $results['observation'] = $obsRes;

        if (isset($obsRes['id'])) {
            $row['ObservationIds'] = [$obsRes['id']];
        }

        // 3. DiagnosticReport
        $drController = new DiagnosticReport();
        $drRes = $drController->push($row, $encounterId);
        $results['diagnostic_report'] = $drRes;

        $isSuccess = isset($srRes['id']) && isset($obsRes['id']) && isset($drRes['id']);

        return $this->response->setJSON([
            'status' => $isSuccess ? 'success' : 'partial',
            'results' => $results
        ]);
    }
}
