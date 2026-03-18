<?php

namespace App\Controllers\Api\SatuSehat\MedicationStatement;

use App\Services\SatusehatService;

abstract class MedicationStatementBase
{
    protected $service;

    public function __construct(SatusehatService $service)
    {
        $this->service = $service;
    }

    protected function sendFHIRMedicationStatement($payload)
    {
        $extraHeaders = [];

        $identifier = $payload['identifier'][0] ?? null;
        $identifierSystem = is_array($identifier) ? trim((string) ($identifier['system'] ?? '')) : '';
        $identifierValue = is_array($identifier) ? trim((string) ($identifier['value'] ?? '')) : '';

        if ($identifierSystem !== '' && $identifierValue !== '') {
            $extraHeaders['If-None-Exist'] = 'identifier=' . $identifierSystem . '|' . $identifierValue;
        }

        try {
            $response = $this->service->post('MedicationStatement', $payload, $extraHeaders);
        } catch (\Exception $e) {
            if (!empty($extraHeaders)) {
                try {
                    $response = $this->service->post('MedicationStatement', $payload);
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
}
