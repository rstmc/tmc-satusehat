<?php

namespace App\Controllers\Api\SatuSehat\Immunization\VariasiPelaporan;

use App\Controllers\Api\SatuSehat\Observation\ObservationBase;

class KIPITerpantauNakes extends ObservationBase
{
    public function push($row, $encounterId)
    {
        // Validate required fields
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        $orgId = getenv('SATUSEHAT_ORG_ID');
        
        // Use provided ID or generate one if missing
        $observationId = $row['ObservationId'] ?? uniqid();
        
        // Format dates
        $effectiveDateTime = isset($row['ObservationDate']) ? date('Y-m-d', strtotime($row['ObservationDate'])) : date('Y-m-d');
        $issued = isset($row['IssuedDate']) ? date('c', strtotime($row['IssuedDate'])) : date('c');

        $payload = [
            "resourceType" => "Observation",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/observation/" . $orgId,
                    "value" => $observationId
                ]
            ],
            "status" => "final",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/observation-category",
                            "code" => "exam",
                            "display" => "exam"
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "31044-1",
                        "display" => "Immunization reaction"
                    ]
                ]
            ],
            "subject" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? ''),
                "display" => $row['Firstname'] ?? ''
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
            "valueCodeableConcept" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => $row['ReactionCode'] ?? 'LA7460-4',
                        "display" => $row['ReactionDisplay'] ?? 'Pain'
                    ]
                ]
            ]
        ];

        return $this->sendFHIRObservation($payload);
    }
}
