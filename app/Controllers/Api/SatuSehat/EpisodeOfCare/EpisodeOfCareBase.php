<?php

namespace App\Controllers\Api\SatuSehat\EpisodeOfCare;

use App\Services\SatusehatService;

abstract class EpisodeOfCareBase
{
    protected $service;

    public function __construct(SatusehatService $service)
    {
        $this->service = $service;
    }

    protected function sendFHIREpisodeOfCare($payload)
    {
        try {
            $response = $this->service->post('EpisodeOfCare', $payload);
            if (isset($response['id'])) {
                return ['status' => 'success', 'id' => $response['id']];
            } else {
                return ['status' => 'failed', 'response' => $response];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    abstract public function push($row, $encounterId, $keluhanUtamaId);
}
