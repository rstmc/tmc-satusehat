<?php

namespace App\Controllers\Api\SatuSehat\Condition\Anamesis;

use App\Controllers\Api\SatuSehat\Condition\ConditionBase;

class RiwayatPenyakitTerdahulu extends ConditionBase
{
    public function push($row, $encounterId)
    {
        if (empty($row['RiwayatPenyakitTerdahulu'])) {
            return null;
        }

        $recordedDate = $this->service->sanitizeFhirDateTime(
            $row['RegDate'] ?? $row['Regdate'] ?? null,
            $row['RegTime'] ?? $row['Regtime'] ?? null
        );

        $payload = [
            "resourceType" => "Condition",
            "clinicalStatus" => [
                "coding" => [
                    ["system" => "http://terminology.hl7.org/CodeSystem/condition-clinical", "code" => "inactive", "display" => "Inactive"]
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
                    ["system" => "http://snomed.info/sct", "code" => (!empty($row['SnomedCodeRPT']) ? $row['SnomedCodeRPT'] : '404684003'), "display" => (!empty($row['SnomedDisplayRPT']) ? $row['SnomedDisplayRPT'] : 'Clinical finding')]
                ]
            ],
            "subject" => ["reference" => "Patient/" . $row['IHSSatuSehat'], "display" => $row['Firstname']],
            "encounter" => ["reference" => "Encounter/" . $encounterId],
            "onsetAge" => [
                "value" => (int)($row['OnsetAgeValue'] ?? 0),
                "unit" => $row['OnsetAgeUnit'] ?? 'years',
                "system" => "http://unitsofmeasure.org",
                "code" => $row['OnsetAgeCode'] ?? 'a'
            ],
            "recordedDate" => $recordedDate,
            "recorder" => ["reference" => "Practitioner/" . $row['KdDocSatuSehat'], "display" => $row['NmDoc']],
            "note" => [
                ["text" => $row['RiwayatPenyakitTerdahulu']]
            ]
        ];

        return $this->sendFHIRCondition($payload);
    }
}
