<?php

namespace App\Controllers\Api\SatuSehat\Condition;

class EncounterDiagnosis extends ConditionBase
{
    public function buildPayload($row, $encounterId)
    {
        if (empty($row['KdIcd'])) {
            return null;
        }

        $orgId = getenv('SATUSEHAT_ORG_ID');

        $payload = [
            "resourceType" => "Condition",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/condition/" . $orgId,
                    "value"  => $row['Regno'] . '-diagnosis-' . $row['KdIcd'],
                ]
            ],
            "clinicalStatus" => [
                "coding" => [
                    ["system" => "http://terminology.hl7.org/CodeSystem/condition-clinical", "code" => "active", "display" => "Active"]
                ]
            ],
            "category" => [
                [
                    "coding" => [
                        ["system" => "http://terminology.hl7.org/CodeSystem/condition-category", "code" => "encounter-diagnosis", "display" => "Encounter Diagnosis"]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    ["system" => "http://hl7.org/fhir/sid/icd-10", "code" => $row['KdIcd'], "display" => $row['NmIcd']]
                ]
            ],
            "subject" => ["reference" => "Patient/" . $row['IHSSatuSehat'], "display" => $row['Firstname']],
            "encounter" => ["reference" => "Encounter/" . $encounterId, "display" => "Kunjungan " . $row['Firstname']]
        ];

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);
        if ($payload === null) {
            return null;
        }

        return $this->sendFHIRCondition($payload);
    }
}
