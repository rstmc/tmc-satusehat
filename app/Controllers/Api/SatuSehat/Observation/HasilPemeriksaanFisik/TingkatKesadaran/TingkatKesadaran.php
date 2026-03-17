<?php

namespace App\Controllers\Api\SatuSehat\Observation\HasilPemeriksaanFisik\TingkatKesadaran;

use App\Controllers\Api\SatuSehat\Observation\ObservationBase;

class TingkatKesadaran extends ObservationBase
{
    public function push($row, $encounterId)
    {
        // Pastikan nilai Kesadaran ada
        if (empty($row['Kesadaran'])) {
            return null;
        }

        // Mapping Kesadaran ke SNOMED CT
        // Default ke Compos Mentis / Mentally Alert jika tidak ada mapping yang cocok atau sesuaikan logic
        $kesadaranMap = [
            'Compos Mentis' => ['code' => '248234008', 'display' => 'Mentally alert'],
            'Alert' => ['code' => '248234008', 'display' => 'Mentally alert'],
            'Sadar' => ['code' => '248234008', 'display' => 'Mentally alert'],
            // Tambahkan mapping lain sesuai kebutuhan
        ];

        $kesadaranText = $row['Kesadaran'];
        $snomed = $kesadaranMap[$kesadaranText] ?? ['code' => '248234008', 'display' => 'Mentally alert']; // Default fallback

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
                        "code" => "67775-7",
                        "display" => "Level of responsiveness"
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
            "valueCodeableConcept" => [
                "coding" => [
                    [
                        "system" => "http://snomed.info/sct",
                        "code" => $snomed['code'],
                        "display" => $snomed['display']
                    ]
                ]
            ]
        ];

        return $this->sendFHIRObservation($payload);
    }
}
