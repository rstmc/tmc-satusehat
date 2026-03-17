<?php

namespace App\Controllers\Api\SatuSehat\NutritionOrder;

class Diet extends NutritionOrderBase
{
    public function push($row, $encounterId)
    {
        // Format DateTime
        $dateOnly = date('Y-m-d', strtotime($row['Regdate']));
        $timeOnly = date('H:i:s', strtotime($row['RegTime']));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $effectiveDateTime = date('c', strtotime($dateTimeStr));

        $payload = [
            "resourceType" => "NutritionOrder",
            "status" => "active",
            "intent" => "proposal",
            "patient" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? '')
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "dateTime" => $effectiveDateTime,
            "orderer" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? '')
            ],
            "excludeFoodModifier" => [
                [
                    "coding" => [
                        [
                            "system" => "http://snomed.info/sct",
                            "code" => "1296980001",
                            "display" => "Soft drink"
                        ]
                    ]
                ]
            ],
            "oralDiet" => [
                "type" => [
                    [
                        "coding" => [
                            [
                                "system" => "http://snomed.info/sct",
                                "code" => "113148007",
                                "display" => "Fluid intake encouragement"
                            ]
                        ]
                    ]
                ],
                "nutrient" => [
                    [
                        "modifier" => [
                            "coding" => [
                                [
                                    "system" => "http://snomed.info/sct",
                                    "code" => "444695006",
                                    "display" => "Mineral water"
                                ]
                            ]
                        ],
                        "amount" => [
                            "value" => 2,
                            "unit" => "L",
                            "system" => "http://unitsofmeasure.org",
                            "code" => "L"
                        ]
                    ]
                ]
            ]
        ];

        return $this->sendFHIRNutritionOrder($payload);
    }
}
