<?php

namespace App\Controllers\Api\SatuSehat\Goal\TujuanPerawatan;

use App\Controllers\Api\SatuSehat\Goal\GoalBase;

class TujuanPerawatan extends GoalBase
{
    public function buildPayload($row, $encounterId, $conditionId = null)
    {
        if (empty($row['IHSSatuSehat']) || empty($row['Planning'])) {
            return null;
        }

        $dateOnly = date('Y-m-d', strtotime($row['Regdate']));
        $dueDate = $dateOnly;
        $orgId = env('SATUSEHAT_ORG_ID') ?: getenv('SATUSEHAT_ORG_ID') ?: ($_ENV['SATUSEHAT_ORG_ID'] ?? '');

        $payload = [
            "resourceType" => "Goal",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/goal/" . $orgId,
                    "use" => "official",
                    "value" => $row['Regno'] . '-tujuan_perawatan'
                ]
            ],
            "lifecycleStatus" => "planned",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/goal-category",
                            "code" => "nursing",
                            "display" => "Nursing"
                        ]
                    ]
                ]
            ],
            "description" => [
                "text" => $row['Planning'] ?? ""
            ],
            "subject" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? ''),
                "display" => $row['Firstname'] ?? ''
            ],
            "target" => [
                [
                    "measure" => [
                        "coding" => [
                            [
                                "system" => "http://loinc.org",
                                "code" => "8480-6",
                                "display" => "Systolic blood pressure"
                            ]
                        ]
                    ],
                    "detailCodeableConcept" => [
                        "coding" => [
                            [
                                "system" => "http://snomed.info/sct",
                                "code" => "17621005",
                                "display" => "Normal"
                            ]
                        ]
                    ],
                    "dueDate" => $dueDate
                ],
                [
                    "measure" => [
                        "coding" => [
                            [
                                "system" => "http://loinc.org",
                                "code" => "8462-4",
                                "display" => "Diastolic blood pressure"
                            ]
                        ]
                    ],
                    "detailCodeableConcept" => [
                        "coding" => [
                            [
                                "system" => "http://snomed.info/sct",
                                "code" => "17621005",
                                "display" => "Normal"
                            ]
                        ]
                    ],
                    "dueDate" => $dueDate
                ],
                [
                    "measure" => [
                        "coding" => [
                            [
                                "system" => "http://loinc.org",
                                "code" => "26515-7",
                                "display" => "Platelets [#/volume] in Blood"
                            ]
                        ]
                    ],
                    "detailCodeableConcept" => [
                        "coding" => [
                            [
                                "system" => "http://snomed.info/sct",
                                "code" => "17621005",
                                "display" => "Normal"
                            ]
                        ]
                    ],
                    "dueDate" => $dueDate
                ]
            ],
            "statusDate" => $dueDate,
            "expressedBy" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? '')
            ]
        ];

        // Add addresses (Condition reference) if available
        if ($conditionId) {
            $payload['addresses'] = [
                [
                    "reference" => "Condition/" . $conditionId
                ]
            ];
        }

        return $payload;
    }

    public function push($row, $encounterId, $conditionId = null)
    {
        $payload = $this->buildPayload($row, $encounterId, $conditionId);
        $id = $row['Goal_TujuanPerawatan'] ?? null;
        $method = $id ? 'PUT' : 'POST';
        
        return $this->sendFHIRGoal($payload, $method, $id);
    }
}
