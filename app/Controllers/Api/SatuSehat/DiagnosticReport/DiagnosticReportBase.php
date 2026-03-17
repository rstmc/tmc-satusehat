<?php

namespace App\Controllers\Api\SatuSehat\DiagnosticReport;

use App\Services\SatusehatService;

abstract class DiagnosticReportBase
{
    protected $service;

    public function __construct(SatusehatService $service)
    {
        $this->service = $service;
    }

    protected function sendFHIRDiagnosticReport($payload)
    {
        try {
            $response = $this->service->post('DiagnosticReport', $payload);
            if (isset($response['id'])) {
                return ['status' => 'success', 'id' => $response['id']];
            } else {
                return ['status' => 'failed', 'response' => $response];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    abstract public function push($row, $encounterId);
}
