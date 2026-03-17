<?php

namespace App\Controllers\Api\SatuSehat\Observation;

use App\Services\SatusehatService;

abstract class ObservationBase
{
    protected $service;

    public function __construct(SatusehatService $service)
    {
        $this->service = $service;
    }

    protected function sendFHIRObservation($payload)
    {
        try {
            $response = $this->service->post('Observation', $payload);
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
