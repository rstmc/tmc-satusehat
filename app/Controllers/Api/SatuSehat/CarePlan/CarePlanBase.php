<?php

namespace App\Controllers\Api\SatuSehat\CarePlan;

use App\Services\SatusehatService;

abstract class CarePlanBase
{
    protected $service;

    public function __construct(SatusehatService $service)
    {
        $this->service = $service;
    }

    protected function sendFHIRCarePlan($payload)
    {
        try {
            $response = $this->service->post('CarePlan', $payload);
            if (isset($response['id'])) {
                return ['status' => 'success', 'id' => $response['id']];
            } else {
                return ['status' => 'failed', 'response' => $response];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    abstract public function push($row, $encounterId, $goalId = null);
}
