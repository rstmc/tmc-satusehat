<?php

namespace App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanFisikHeadToToe;

use App\Controllers\Api\SatuSehat\Observation\ObservationBase;

class Telinga extends ObservationBase
{
    public function push($row, $encounterId)
    {
        // Pastikan nilai Telinga ada
        if (empty($row['Telinga'])) {
            return null;
        }

        // Format tanggal dan waktu
        $dateOnly = date('Y-m-d', strtotime($row['Regdate']));
        $timeOnly = date('H:i:s', strtotime($row['RegTime']));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $effectiveDateTime = date('c', strtotime($dateTimeStr));

        $payload = [
            "resourceType" => "Observation",
            "status" => "final",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/observation-category",
                            "code" => "exam",
                            "display" => "Exam"
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "10195-6",
                        "display" => "Physical findings of Ear Narrative"
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
            "issued" => $effectiveDateTime,
            "performer" => [
                [
                    "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? ''),
                    "display" => $row['NmDoc'] ?? ''
                ]
            ],
            "valueString" => $row['Telinga']
        ];

        return $this->sendFHIRObservation($payload);
    }
}
