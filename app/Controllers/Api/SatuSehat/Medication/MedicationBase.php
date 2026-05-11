<?php

namespace App\Controllers\Api\SatuSehat\Medication;

use App\Services\SatusehatService;

abstract class MedicationBase
{
    protected $service;

    public function __construct(SatusehatService $service)
    {
        $this->service = $service;
    }

    protected function sendFHIRMedication($payload, $medicationId = null)
    {
        try {
            if ($medicationId) {
                $payload['id'] = $medicationId;
                $response = $this->service->put('Medication', $medicationId, $payload);
            } else {
                $response = $this->service->post('Medication', $payload);
            }
            
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
