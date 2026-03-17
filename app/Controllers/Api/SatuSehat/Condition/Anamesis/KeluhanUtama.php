<?php

namespace App\Controllers\Api\SatuSehat\Condition\Anamesis;

use App\Controllers\Api\SatuSehat\Condition\ConditionBase;

class KeluhanUtama extends ConditionBase
{
    public function push($row, $encounterId)
    {
        if (empty($row['Subjective'])) {
            return null;
        }

        $dateOnly = date('Y-m-d', strtotime($row['Regdate']));
        $timeOnly = date('H:i:s', strtotime($row['RegTime']));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $recordedDate = date('c', strtotime($dateTimeStr));

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
                        ["system" => "http://terminology.kemkes.go.id", "code" => "chief-complaint", "display" => "Chief Complaint"]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    ["system" => "http://snomed.info/sct", "code" => $row['SnomedCodeKeluhanUtama'] ?? 'unknown', "display" => $row['SnomedDisplayKeluhanUtama'] ?? 'Unknown']
                ]
            ],
            "subject" => ["reference" => "Patient/" . $row['IHSSatuSehat'], "display" => $row['Firstname']],
            "encounter" => ["reference" => "Encounter/" . $encounterId],
            "onsetDateTime" => $recordedDate,
            "recordedDate" => $recordedDate,
            "recorder" => ["reference" => "Practitioner/" . $row['KdDocSatuSehat'], "display" => $row['NmDoc']],
            "note" => [
                ["text" => $row['Subjective']]
            ]
        ];

        return $this->sendFHIRCondition($payload);
    }
}
