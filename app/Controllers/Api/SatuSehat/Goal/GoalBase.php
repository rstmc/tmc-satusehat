<?php

namespace App\Controllers\Api\SatuSehat\Goal;

use App\Services\SatusehatService;

abstract class GoalBase
{
    protected $service;

    public function __construct(SatusehatService $service)
    {
        $this->service = $service;
    }

    protected function sendFHIRGoal($payload, $method = 'POST', $id = null)
    {
        try {
            if ($method === 'PUT' && $id) {
                $response = $this->service->put('Goal', $id, $payload);
            } else {
                $response = $this->service->post('Goal', $payload);
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
