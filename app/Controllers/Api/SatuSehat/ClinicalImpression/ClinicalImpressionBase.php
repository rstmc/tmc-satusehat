<?php

namespace App\Controllers\Api\SatuSehat\ClinicalImpression;

use App\Services\SatusehatService;

abstract class ClinicalImpressionBase
{
    protected $service;

    public function __construct(SatusehatService $service)
    {
        $this->service = $service;
    }

    protected function sendFHIRClinicalImpression($payload)
    {
        try {
            $response = $this->service->post('ClinicalImpression', $payload);
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
