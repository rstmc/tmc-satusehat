<?php

namespace App\Controllers\Api\SatuSehat\Observation\Laboratorium;

use App\Controllers\Api\SatuSehat\Observation\ObservationBase;
use App\Services\SatusehatService;

class LaboratoriumObservation extends ObservationBase
{
    public function __construct()
    {
        parent::__construct(new SatusehatService());
    }

    public function push($row, $encounterId)
    {
        // $row here is a detail row + header info
        $orgId = getenv('SATUSEHAT_ORG_ID');
        // Unique ID: NoTran + KdPemeriksaan (or some detail ID if available)
        // If KdPemeriksaan is not unique enough, consider using id from DetailBilLab if available
        $obsId = $row['NoTran'] . '-' . ($row['KdPemeriksaan'] ?? uniqid());

        $effectiveDateTime = date('c', strtotime($row['TglHasil'] ?? date('c')));
        $issued = $effectiveDateTime;

        // LOINC Code Mapping
        // Ideally this should come from DB or a mapping file.
        // Using provided code or default.
        $loincCode = $row['LoincCode'] ?? 'Unknown';
        $loincDisplay = $row['NmTarif'] ?? 'Laboratory Test';
        
        // Value
        // Assuming result value is in 'IsiHasil' (from header?) or we need to find it in detail.
        // User didn't provide explicit 'ResultValue' column in DetailBilLab.
        // I will use 'Hasil' if it exists in row, otherwise 'Unknown'.
        // Some systems put results in 'IsiHasil' column of Header if it's a single result, or elsewhere.
        $valueString = $row['Hasil'] ?? $row['IsiHasil'] ?? 'Pending';

        $payload = [
            "resourceType" => "Observation",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/observation/" . $orgId,
                    "value" => $obsId
                ]
            ],
            "status" => "final",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/observation-category",
                            "code" => "laboratory",
                            "display" => "Laboratory"
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => $loincCode,
                        "display" => $loincDisplay
                    ]
                ],
                "text" => $loincDisplay
            ],
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat']
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "effectiveDateTime" => $effectiveDateTime,
            "issued" => $issued,
            "performer" => [
                [
                    "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? '')
                ],
                [
                    "reference" => "Organization/" . $orgId
                ]
            ],
            "valueString" => $valueString
        ];
        
        if (!empty($row['SpecimenId'])) {
            $payload['specimen'] = [
                "reference" => "Specimen/" . $row['SpecimenId']
            ];
        }
        
        if (!empty($row['ServiceRequestId'])) {
            $payload['basedOn'] = [
                [
                    "reference" => "ServiceRequest/" . $row['ServiceRequestId']
                ]
            ];
        }

        return $this->sendFHIRObservation($payload);
    }
}
