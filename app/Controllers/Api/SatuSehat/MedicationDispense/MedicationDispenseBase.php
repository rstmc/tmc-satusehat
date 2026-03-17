<?php

namespace App\Controllers\Api\SatuSehat\MedicationDispense;

use App\Services\SatusehatService;

abstract class MedicationDispenseBase
{
    protected $service;

    public function __construct(SatusehatService $service)
    {
        $this->service = $service;
    }

    protected function sendFHIRMedicationDispense($payload)
    {
        try {
            $response = $this->service->post('MedicationDispense', $payload);
            if (isset($response['id'])) {
                return ['status' => 'success', 'id' => $response['id']];
            } else {
                return ['status' => 'failed', 'response' => $response];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    abstract public function push($row, $encounterId, $medRequestId = null);
}
