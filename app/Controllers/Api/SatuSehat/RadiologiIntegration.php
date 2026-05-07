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
}
