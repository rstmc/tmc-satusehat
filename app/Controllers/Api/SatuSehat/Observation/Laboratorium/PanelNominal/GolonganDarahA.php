<?php

namespace App\Controllers\Api\SatuSehat\Observation\Laboratorium\PanelNominal;

use App\Controllers\Api\SatuSehat\Observation\ObservationBase;

class GolonganDarahA extends ObservationBase
{
    public function push($row, $encounterId)
    {
        // Validate required fields
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        // Format dates
        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $effectiveDateTime = date('c', strtotime($dateTimeStr));
        $issued = $effectiveDateTime;

        $orgId = getenv('SATUSEHAT_ORG_ID');
        // Use provided ID or generate one if missing
        $observationId = $row['Lab_ObsID_Nominal'] ?? $row['ObservationId'] ?? uniqid();

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
                        "code" => "57743-7",
                        "display" => "ABO group [Type] in Blood by Confirmatory method"
                    ]
                ]
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
            "specimen" => [
                "reference" => "Specimen/" . ($row['Specimen_Nominal'] ?? '')
            ],
            "basedOn" => [
                [
                    "reference" => "ServiceRequest/" . ($row['ServiceRequest_Nominal'] ?? '')
                ]
            ],
            "valueCodeableConcept" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "LA19710-5",
                        "display" => "Group A"
                    ]
                ]
            ]
        ];

        return $this->sendFHIRObservation($payload);
    }
}
