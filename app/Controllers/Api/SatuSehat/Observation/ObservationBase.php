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

    protected function patchFHIRObservation(string $id, array $payload)
    {
        try {
            $response = $this->service->patch('Observation', $id, $payload);
            if (isset($response['id'])) {
                return ['status' => 'success', 'id' => $response['id'], 'action' => 'patched'];
            } else {
                return ['status' => 'failed', 'response' => $response];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    abstract public function push($row, $encounterId);

    public function patch($id, $row)
    {
        if (empty($id)) {
            return [
                'status'  => 'error',
                'message' => 'Observation ID is required for PATCH'
            ];
        }

        if (isset($row[0]['op'])) {
            return $this->patchFHIRObservation($id, $row);
        }

        $operations = [];
        if (isset($row['valueString']) || isset($row['Hasil'])) {
            $operations[] = [
                'op'    => 'replace',
                'path'  => '/valueString',
                'value' => (string)($row['valueString'] ?? $row['Hasil'] ?? '')
            ];
        }
        if (isset($row['status'])) {
            $operations[] = [
                'op'    => 'replace',
                'path'  => '/status',
                'value' => $row['status']
            ];
        }

        if (empty($operations)) {
            return [
                'status' => 'success',
                'id'     => $id,
                'action' => 'no_change'
            ];
        }

        return $this->patchFHIRObservation($id, $operations);
    }
}
