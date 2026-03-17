<?php

namespace App\Controllers\Api\SatuSehat\Goal\ProgressTujuanPerawatan;

use App\Controllers\Api\SatuSehat\Goal\GoalBase;

class TujuanPerawatan extends GoalBase
{
    public function push($row, $encounterId)
    {
        // Validasi ID Goal untuk update
        if (empty($row['Goal_TujuanPerawatan'])) {
            return ['status' => 'failed', 'message' => 'Goal ID is required for update'];
        }

        $payload = [
            "resourceType" => "Goal",
            "id" => $row['Goal_TujuanPerawatan'],
            "lifecycleStatus" => "active",
            "achievementStatus" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/goal-achievement",
                        "code" => "in-progress",
                        "display" => "In Progress"
                    ]
                ]
            ],
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
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? '')
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
                    "dueDate" => $row['DueDate'] ?? ""
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
                    "dueDate" => $row['DueDate'] ?? ""
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
                    "dueDate" => $row['DueDate'] ?? ""
                ]
            ],
            "statusDate" => $row['StatusDate'] ?? date('Y-m-d'),
            "expressedBy" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? $row['Practitioner_id'])
            ],
            "addresses" => [
                [
                    "reference" => "Condition/" . ($row['Condition_KeluhanUtama'] ?? '')
                ]
            ],
            "outcomeCode" => [
                [
                    "coding" => [
                        [
                            "system" => "http://snomed.info/sct",
                            "code" => "706907002",
                            "display" => "Some progress toward goal"
                        ]
                    ]
                ]
            ]
        ];

        return $this->sendFHIRGoal($payload, 'PUT', $row['Goal_TujuanPerawatan']);
    }
}
