<?php

namespace App\Controllers\Api\SatuSehat\ClinicalImpression\RiwayatPerjalananPenyakit;

use App\Controllers\Api\SatuSehat\ClinicalImpression\ClinicalImpressionBase;

class RiwayatPerjalananPenyakit extends ClinicalImpressionBase
{
    public function buildPayload($row, $encounterId)
    {
        if (empty($row['Assessment'])) {
            return null;
        }

        $dateOnly = date('Y-m-d', strtotime($row['Regdate']));
        $timeOnly = date('H:i:s', strtotime($row['RegTime']));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $effectiveDateTime = date('c', strtotime($dateTimeStr));

        $payload = [
            "resourceType" => "ClinicalImpression",
            "status" => "completed",
            "code" => [
                "coding" => [
                    [
                        "system" => "http://snomed.info/sct",
                        "code" => "312850006",
                        "display" => "History of disorder"
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
            "date" => $effectiveDateTime,
            "assessor" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? '')
            ],
            "summary" => $row['Assessment']
        ];

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);
        if ($payload === null) {
            return null;
        }

        return $this->sendFHIRClinicalImpression($payload);
    }
}
