<?php

namespace App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\PemeriksaanTandaVital;

use App\Controllers\Api\SatuSehat\Observation\ObservationBase;

class DenyutJantung extends ObservationBase
{
    public function push($row, $encounterId)
    {
        // Pastikan nilai Nadi ada
        if (empty($row['Nadi'])) {
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
                            "code" => "vital-signs",
                            "display" => "Vital Signs"
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "8867-4",
                        "display" => "Heart rate"
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
            "valueQuantity" => [
                "value" => (float)$row['Nadi'],
                "unit" => "{beats}/min",
                "system" => "http://unitsofmeasure.org",
                "code" => "{beats}/min"
            ]
        ];

        return $this->sendFHIRObservation($payload);
    }
}
