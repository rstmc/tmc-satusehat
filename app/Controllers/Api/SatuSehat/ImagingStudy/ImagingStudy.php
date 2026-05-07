<?php

namespace App\Controllers\Api\SatuSehat\ImagingStudy;

use App\Services\SatusehatService;

class ImagingStudy
{
    protected $service;

    public function __construct()
    {
        $this->service = new SatusehatService();
    }

    public function searchByAcsn($acsn)
    {
        $orgId = getenv('SATUSEHAT_ORG_ID');
        $identifier = "http://sys-ids.kemkes.go.id/acsn/" . $orgId . "|" . $acsn;

        try {
            $response = $this->service->get('ImagingStudy', [
                'identifier' => $identifier
            ]);

            if (isset($response['entry']) && count($response['entry']) > 0) {
                return [
                    'status' => 'success',
                    'data' => $response['entry'][0]['resource']
                ];
            } else {
                return [
                    'status' => 'not_found',
                    'message' => 'ImagingStudy not found for ACSN: ' . $acsn
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
