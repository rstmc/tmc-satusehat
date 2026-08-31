<?php

namespace App\Controllers\Api\SatuSehat;

use App\Controllers\BaseController;
use App\Services\SatusehatService;
use App\Controllers\Api\SatuSehat\ServiceRequest\Laboratorium\LaboratoriumServiceRequest;
use App\Controllers\Api\SatuSehat\Specimen\Laboratorium\LaboratoriumSpecimen;
use App\Controllers\Api\SatuSehat\Observation\Laboratorium\LaboratoriumObservation;
use App\Controllers\Api\SatuSehat\DiagnosticReport\Laboratorium\LaboratoriumDiagnosticReport;

class LaboratoriumIntegration extends BaseController
{
    protected $service;

    public function __construct()
    {
        $this->service = new SatusehatService();
    }

    // ============================================================
    // Push individual (step by step)
    // ============================================================

    /**
     * POST api/satusehat/laboratorium/service-request
     * Body: { ...row_data, encounter_id: "..." }
     */
    public function pushServiceRequest()
    {
        $data = $this->request->getJSON(true);
        $encounterId = $data['encounter_id'] ?? null;
        $controller  = new LaboratoriumServiceRequest();
        $res         = $controller->push($data, $encounterId);
        return $this->response->setJSON($res);
    }

    /**
     * POST api/satusehat/laboratorium/specimen
     * Body: { ...row_data, encounter_id: "..." }
     */
    public function pushSpecimen()
    {
        $data = $this->request->getJSON(true);
        $encounterId = $data['encounter_id'] ?? null;
        $controller  = new LaboratoriumSpecimen();
        $res         = $controller->push($data, $encounterId);
        return $this->response->setJSON($res);
    }

    /**
     * POST api/satusehat/laboratorium/observation
     * Body: { ...row_data (single detail), encounter_id: "..." }
     */
    public function pushObservation()
    {
        $data = $this->request->getJSON(true) ?: [];
        $encounterId = $data['encounter_id'] ?? null;
        $controller  = new LaboratoriumObservation();
        
        $obsId = $data['id'] ?? $data['SatusehatObservationId'] ?? $data['observation_id'] ?? null;
        if (!empty($obsId)) {
            $res = $controller->patch($obsId, $data);
        } else {
            $res = $controller->push($data, $encounterId);
        }
        return $this->response->setJSON($res);
    }

    public function patchObservation($id = null)
    {
        $data = $this->request->getJSON(true) ?: [];
        $obsId = $id ?: ($data['id'] ?? $data['SatusehatObservationId'] ?? $data['observation_id'] ?? $this->request->getGet('id'));

        if (!$obsId) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Observation ID is required for PATCH'
            ])->setStatusCode(400);
        }

        $controller = new LaboratoriumObservation();
        $res = $controller->patch($obsId, $data);
        return $this->response->setJSON($res);
    }

    /**
     * POST api/satusehat/laboratorium/diagnostic-report
     * Body: { ...row_data, encounter_id, SpecimenId, ServiceRequestId, ObservationIds: [] }
     */
    public function pushDiagnosticReport()
    {
        $data = $this->request->getJSON(true);
        $encounterId = $data['encounter_id'] ?? null;
        $controller  = new LaboratoriumDiagnosticReport();
        $res         = $controller->push($data, $encounterId);
        return $this->response->setJSON($res);
    }

    // ============================================================
    // Push Complete (1 kali klik, semua resource sekaligus)
    // Mirip pushRadiologyComplete
    // ============================================================

    /**
     * POST api/satusehat/laboratorium/push-complete
     * Body JSON:
     * {
     *   "notran": "LAB2026...",
     *   "encounter_id": "uuid-encounter",
     *   "row": { ...semua kolom HeadBilLab + JOIN AsalSampel, dll }
     *   "details": [ { ...detail baris 1 }, { ...detail baris 2 } ]
     * }
     */
    public function pushLabComplete()
    {
        $body = $this->request->getJSON(true);

        $notran      = $body['notran'] ?? null;
        $encounterId = $body['encounter_id'] ?? null;
        $row         = $body['row'] ?? [];
        $details     = $body['details'] ?? [];

        if (!$notran || !$encounterId || empty($row)) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'notran, encounter_id, dan row wajib diisi.',
            ])->setStatusCode(400);
        }

        $results = [];

        // -----------------------------------------------------------
        // STEP 1: ServiceRequest
        // -----------------------------------------------------------
        if (!empty($row['SatusehatServiceRequestId'])) {
            $srRes = ['status' => 'success', 'id' => $row['SatusehatServiceRequestId']];
        } else {
            $srController = new LaboratoriumServiceRequest();
            $srRes        = $srController->push($row, $encounterId);
        }
        $results['service_request'] = $srRes;

        if (isset($srRes['id'])) {
            $row['ServiceRequestId'] = $srRes['id'];
        }

        // -----------------------------------------------------------
        // STEP 2: Specimen
        // -----------------------------------------------------------
        if (!empty($row['SatusehatSpecimenId'])) {
            $specRes = ['status' => 'success', 'id' => $row['SatusehatSpecimenId']];
        } else {
            $specController = new LaboratoriumSpecimen();
            $specRes        = $specController->push($row, $encounterId);
        }
        $results['specimen'] = $specRes;

        if (isset($specRes['id'])) {
            $row['SpecimenId'] = $specRes['id'];
        }

        // -----------------------------------------------------------
        // STEP 3: Observation per item detail
        // -----------------------------------------------------------
        $observationIds  = [];
        $observationResults = [];

        foreach ($details as $detail) {
            // Merge header row ke detail agar controller dapat semua data
            $detailRow = array_merge($row, $detail);
            $detailRow['SpecimenId'] = $row['SpecimenId'] ?? null;

            // Jika Observation sudah pernah berhasil dikirim, lakukan PATCH untuk update hasil baru
            if (!empty($detail['SatusehatObservationId'])) {
                $obsController = new LaboratoriumObservation();
                $obsRes        = $obsController->patch($detail['SatusehatObservationId'], $detailRow);
                $obsId         = $obsRes['id'] ?? $detail['SatusehatObservationId'];
                $observationIds[] = $obsId;
                $observationResults[] = [
                    'KdPemeriksaan' => $detail['KdPemeriksaan'] ?? '-',
                    'NmPemeriksaan' => $detail['NmTarif'] ?? '',
                    'status'        => $obsRes['status'] ?? 'success',
                    'id'            => $obsId,
                    'action'        => $obsRes['action'] ?? 'patched',
                    'response'      => $obsRes['response'] ?? ($obsRes['message'] ?? null),
                ];
                continue;
            }

            $obsController = new LaboratoriumObservation();
            $obsRes        = $obsController->push($detailRow, $encounterId);

            if (isset($obsRes['id'])) {
                $observationIds[] = $obsRes['id'];
            }

            $observationResults[] = [
                'KdPemeriksaan' => $detail['KdPemeriksaan'] ?? '-',
                'NmPemeriksaan' => $detail['NmTarif'] ?? '',
                'status'        => $obsRes['status'] ?? 'error',
                'id'            => $obsRes['id'] ?? null,
                'response'      => $obsRes['response'] ?? ($obsRes['message'] ?? null),
            ];
        }
        $results['observations'] = $observationResults;
        $row['ObservationIds']   = $observationIds;

        // -----------------------------------------------------------
        // STEP 4: DiagnosticReport
        // -----------------------------------------------------------
        if (!empty($row['SatusehatDiagnosticReportId'])) {
            $drRes = ['status' => 'success', 'id' => $row['SatusehatDiagnosticReportId']];
        } else {
            $drController = new LaboratoriumDiagnosticReport();
            $drRes        = $drController->push($row, $encounterId);
        }
        $results['diagnostic_report'] = $drRes;

        // -----------------------------------------------------------
        // Tentukan status keseluruhan
        // -----------------------------------------------------------
        $isSuccess = isset($srRes['id'])
            && isset($specRes['id'])
            && count($observationIds) > 0
            && isset($drRes['id']);

        return $this->response->setJSON([
            'status'  => $isSuccess ? 'success' : 'partial',
            'message' => $isSuccess
                ? 'Semua resource FHIR laboratorium berhasil dikirim ke SATUSEHAT.'
                : 'Sebagian resource gagal dikirim. Periksa detail results.',
            'results' => $results,
        ]);
    }
}
