<?php

namespace App\Controllers\Api\SatuSehat\Condition;

class MeninggalkanFaskes extends ConditionBase
{
    public function push($row, $encounterId)
    {
        $payload = [
            "resourceType" => "Condition",
            "clinicalStatus" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/condition-clinical",
                        "code" => "active",
                        "display" => "Active"
                    ]
                ]
            ],
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/condition-category",
                            "code" => "encounter-diagnosis",
                            "display" => "Encounter Diagnosis"
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://snomed.info/sct",
                        "code" => "359746009",
                        "display" => "Patient's condition stable"
                    ]
                ]
            ],
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat'],
                "display" => $row['Firstname']
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId,
                "display" => "Kunjungan " . $row['Firstname']
            ]
        ];

        return $this->sendFHIRCondition($payload);
    }
}
