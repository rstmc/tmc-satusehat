<?php

namespace App\Controllers\Api\SatuSehat\Specimen\Laboratorium;

use App\Controllers\Api\SatuSehat\Specimen\SpecimenBase;
use App\Services\SatusehatService;

class LaboratoriumSpecimen extends SpecimenBase
{
    public function __construct()
    {
        parent::__construct(new SatusehatService());
    }

    public function buildPayload($row, $encounterId)
    {
        $orgId = getenv('SATUSEHAT_ORG_ID');
        $specimenId = $row['NoTran'] . '-SPEC'; // Unique ID for Specimen

        // Determine Specimen type
        // HeadBilLab has 'AsalSampel'.
        $specimenType = $row['AsalSampel'] ?? 'Serum';
        $snomedCode = '119364003'; // Default Serum
        $snomedDisplay = 'Serum specimen';

        if (stripos($specimenType, 'Darah') !== false) {
             $snomedCode = '119297000';
             $snomedDisplay = 'Blood specimen';
        } elseif (stripos($specimenType, 'Urin') !== false) {
             $snomedCode = '122575003';
             $snomedDisplay = 'Urine specimen';
        }
        
        $collectionTime = date('c', strtotime($row['TglSampel'] ?? date('c')));

        $payload = [
            "resourceType" => "Specimen",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/specimen/" . $orgId,
                    "value" => $specimenId
                ]
            ],
            "status" => "available",
            "type" => [
                "coding" => [
                    [
                        "system" => "http://snomed.info/sct",
                        "code" => $snomedCode,
                        "display" => $snomedDisplay
                    ]
                ],
                "text" => $specimenType
            ],
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat']
            ],
            "collection" => [
                "collectedDateTime" => $collectionTime
            ]
        ];

        // If ServiceRequest ID is available, link it
        if (!empty($row['ServiceRequestId'])) {
            $payload['request'] = [
                [
                    "reference" => "ServiceRequest/" . $row['ServiceRequestId']
                ]
            ];
        }

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);
        return $this->sendFHIRSpecimen($payload);
    }
}
