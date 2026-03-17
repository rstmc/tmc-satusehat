<?php

namespace App\Controllers\Api\SatuSehat\Goal\TujuanPerawatan;

use App\Controllers\Api\SatuSehat\Goal\GoalBase;

class TujuanPerawatan extends GoalBase
{
    public function push($row, $encounterId, $conditionId = null)
    {
        // Pastikan data minimal tersedia
        // if (empty($row['Planning'])) { return null; } // Optional: uncomment jika ada kolom khusus

        $dateOnly = date('Y-m-d', strtotime($row['Regdate']));
        $dueDate = $dateOnly; // Default dueDate sama dengan tanggal registrasi

        $payload = [
            "resourceType" => "Goal",
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

        $id = $row['Goal_TujuanPerawatan'] ?? null;
        $method = $id ? 'PUT' : 'POST';
        
        return $this->sendFHIRGoal($payload, $method, $id);
    }
}
