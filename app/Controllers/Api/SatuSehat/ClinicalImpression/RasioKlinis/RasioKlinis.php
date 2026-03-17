<?php

namespace App\Controllers\Api\SatuSehat\ClinicalImpression\RasioKlinis;

use App\Controllers\Api\SatuSehat\ClinicalImpression\ClinicalImpressionBase;

class RasioKlinis extends ClinicalImpressionBase
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

        // Observation ID for Investigation
        $observationId = $row['Observation_Kuantitatif'] ?? '';

        $payload = [
            "resourceType" => "ClinicalImpression",
            "status" => "completed",
            "code" => [
                "coding" => [
                    [
                        "system" => "http://terminology.kemkes.go.id",
                        "code" => "TK000056",
                        "display" => "Rasional Klinis"
                    ]
                ]
            ],
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat'],
                "display" => $row['Firstname'] ?? ''
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "effectiveDateTime" => $effectiveDateTime,
            "date" => $effectiveDateTime,
            "assessor" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? '')
            ],
            "investigation" => [
                [
                    "code" => [
                        "coding" => [
                            [
                                "system" => "http://snomed.info/sct",
                                "code" => "271336007",
                                "display" => "Examination / signs"
                            ]
                        ]
                    ],
                    "item" => [
                        [
                            "reference" => "Observation/" . $observationId,
                            "display" => "Hasil Pemeriksaan Penunjang Laboratorium"
                        ]
                    ]
                ]
            ],
            "summary" => $row['Summary'] ?? "Pasien datang dengan keluhan utama demam menggigil disertai sakit kepala. Hasil pemeriksaan penunjang mengarah pada kemungkinan pasien menderita Demam Berdarah"
        ];

        return $this->sendFHIRClinicalImpression($payload);
    }
}
