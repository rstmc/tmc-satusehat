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
        try {
            $response = $this->service->post('MedicationStatement', $payload);
            
            if (isset($response['id'])) {
                return ['status' => 'success', 'id' => $response['id']];
            } else {
                return ['status' => 'failed', 'response' => $response];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
