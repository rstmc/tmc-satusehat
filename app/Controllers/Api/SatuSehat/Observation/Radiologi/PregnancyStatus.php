<?php

namespace App\Controllers\Api\SatuSehat\Observation\Radiologi;

use App\Controllers\Api\SatuSehat\Observation\ObservationBase;
use App\Services\SatusehatService;

class PregnancyStatus extends ObservationBase
{
    public function __construct()
    {
        parent::__construct(new SatusehatService());
    }

    public function push($row, $encounterId)
    {
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $effectiveDateTime = date('c', strtotime($dateOnly . ' ' . $timeOnly));

        $payload = [
            "resourceType" => "Observation",
            "status" => "final",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/observation-category",
                            "code" => "survey",
                            "display" => "Survey"
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "82810-3",
                        "display" => "Pregnancy status"
                    ]
                ]
            ],
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat']
            ],
            "performer" => [
                [
                    "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? '')
                ]
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "effectiveDateTime" => $effectiveDateTime,
            "issued" => $effectiveDateTime,
            "valueCodeableConcept" => [
                "coding" => [
                    [
                        "system" => "http://snomed.info/sct",
                        "code" => "60001007",
                        "display" => "Not pregnant"
                    ]
                ]
            ]
        ];

        return $this->sendFHIRObservation($payload);
    }
}
