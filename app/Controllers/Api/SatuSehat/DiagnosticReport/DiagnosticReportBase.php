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
        $extraHeaders = [];

        $identifier = $payload['identifier'][0] ?? null;
        $identifierSystem = is_array($identifier) ? ($identifier['system'] ?? null) : null;
        $identifierValue = is_array($identifier) ? ($identifier['value'] ?? null) : null;

        if (!empty($identifierSystem) && !empty($identifierValue)) {
            $extraHeaders['If-None-Exist'] = 'identifier=' . $identifierSystem . '|' . $identifierValue;
        }

        try {
            $response = $this->service->post('DiagnosticReport', $payload, $extraHeaders);
        } catch (\Exception $e) {
            if (!empty($extraHeaders)) {
                try {
                    $response = $this->service->post('DiagnosticReport', $payload);
                } catch (\Exception $retryException) {
                    return ['status' => 'error', 'message' => $retryException->getMessage()];
                }
            } else {
                return ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        if (isset($response['id'])) {
            return ['status' => 'success', 'id' => $response['id']];
        }

        return ['status' => 'failed', 'response' => $response];
    }

    abstract public function push($row, $encounterId);
}
