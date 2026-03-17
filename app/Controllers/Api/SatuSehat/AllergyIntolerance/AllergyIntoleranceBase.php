<?php

namespace App\Controllers\Api\SatuSehat\AllergyIntolerance;

use App\Services\SatusehatService;

abstract class AllergyIntoleranceBase
{
    protected $service;

    public function __construct(SatusehatService $service)
    {
        $this->service = $service;
    }

    protected function sendFHIRAllergyIntolerance($payload)
    {
        try {
            $response = $this->service->post('AllergyIntolerance', $payload);
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
