<?php

namespace App\Controllers\Api\SatuSehat\Condition\Anamesis;

use App\Controllers\Api\SatuSehat\Condition\ConditionBase;

class RiwayatPenyakitSekarang extends ConditionBase
{
    public function push($row, $encounterId)
    {
        if (empty($row['RiwayatPenyakitSekarang'])) {
            return null;
        }

        $recordedDate = $this->service->sanitizeFhirDateTime(
            $row['RegDate'] ?? $row['Regdate'] ?? null,
            $row['RegTime'] ?? $row['Regtime'] ?? null
        );

        // Use placeholder start date if available, otherwise default to recordedDate
        $onsetStart = $row['OnsetStartRPS'] ?? $recordedDate;

        $payload = [
            "resourceType" => "Condition",
            "clinicalStatus" => [
                "coding" => [
                    ["system" => "http://terminology.hl7.org/CodeSystem/condition-clinical", "code" => "active", "display" => "Active"]
                ]
            ],
            "category" => [
                [
                    "coding" => [
                        ["system" => "http://terminology.kemkes.go.id", "code" => "previous-condition", "display" => "Previous Condition"]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    ["system" => "http://snomed.info/sct", "code" => (!empty($row['SnomedCodeRPS']) ? $row['SnomedCodeRPS'] : '404684003'), "display" => (!empty($row['SnomedDisplayRPS']) ? $row['SnomedDisplayRPS'] : 'Clinical finding')]
                ]
            ],
            "subject" => ["reference" => "Patient/" . $row['IHSSatuSehat'], "display" => $row['Firstname']],
            "encounter" => ["reference" => "Encounter/" . $encounterId],
            "onsetPeriod" => [
                "start" => $onsetStart,
                "end" => $recordedDate
            ],
            "recordedDate" => $recordedDate,
            "recorder" => ["reference" => "Practitioner/" . $row['KdDocSatuSehat'], "display" => $row['NmDoc']],
            "note" => [
                ["text" => $row['RiwayatPenyakitSekarang']]
            ]
        ];

        return $this->sendFHIRCondition($payload);
    }
}
