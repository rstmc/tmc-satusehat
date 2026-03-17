<?php

namespace App\Controllers\Api\SatuSehat\ClinicalImpression;

use App\Controllers\Api\SatuSehat\ClinicalImpression\ClinicalImpressionBase;

class ClinicalImpression extends ClinicalImpressionBase
{
    public function push($row, $encounterId, $keluhanUtamaId = null)
    {
        // Organization ID from environment or config
        $orgId = getenv('SATUSEHAT_ORG_ID');

        // Timestamps from regdate and regtime + 30 mins
        $regDateInput = $row['RegDate'] ?? $row['Regdate'] ?? date('Y-m-d');
        $regTimeInput = $row['RegTime'] ?? $row['Regtime'] ?? date('H:i:s');
        
        // Normalize date and time inputs to handle potential datetime strings (e.g. "2026-01-02 00:00:00.000")
        $regDate = date('Y-m-d', strtotime($regDateInput));
        $regTime = date('H:i:s', strtotime($regTimeInput));
        
        $timestamp = strtotime("$regDate $regTime") + 1800;
        $effectiveDateTime = date('c', $timestamp);
        $date = date('c', $timestamp);

        // Identifier
        $identifierValue = $row['NoPrognosis'] ?? 'Prognosis_' . date('His');

        // Prognosis SNOMED Code
        $prognosisCode = $row['PrognosisCode'] ?? '170968001';
        $prognosisDisplay = $row['PrognosisDisplay'] ?? 'Prognosis good';

        // Prepare investigation items
        $investigationItems = [];
        if (!empty($row['DiagnosticReportId'])) {
            $investigationItems[] = [
                "reference" => "DiagnosticReport/" . $row['DiagnosticReportId']
            ];
        }
        if (!empty($row['ObservationId'])) {
            $investigationItems[] = [
                "reference" => "Observation/" . $row['ObservationId']
            ];
        }

        // Prepare finding items
        $finding = [];
        if (!empty($row['KdIcd'])) {
            $findingItem = [
                "itemCodeableConcept" => [
                    "coding" => [
                        [
                            "system" => "http://hl7.org/fhir/sid/icd-10",
                            "code" => $row['KdIcd'],
                            "display" => $row['NmIcd'] ?? ''
                        ]
                    ]
                ]
            ];
            
            if (!empty($keluhanUtamaId)) {
                $findingItem["itemReference"] = [
                    "reference" => "Condition/" . $keluhanUtamaId
                ];
            }
            $finding[] = $findingItem;
        }

        // Prepare problem list
        $problem = [];
        // Only add problem if ConditionId is explicitly provided or fall back to default if needed, 
        // but ensure we don't send "Condition/" with empty ID
        $conditionId = $keluhanUtamaId ?? '';
        if (!empty($conditionId)) {
            $problem[] = [
                "reference" => "Condition/" . $conditionId
            ];
        }

        $payload = [
            "resourceType" => "ClinicalImpression",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/clinicalimpression/" . $orgId,
                    "use" => "official",
                    "value" => $identifierValue
                ]
            ],
            "status" => "completed",
            "description" => $row['Assessment'] ?? '',
            "subject" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? ''),
                "display" => $row['Firstname'] ?? ''
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId,
                "display" => "Kunjungan " . ($row['Firstname'] ?? '') . " di hari " . $this->service->formatIndonesianDate($regDate)
            ],
            "effectiveDateTime" => $effectiveDateTime,
            "date" => $date,
            "assessor" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? 'N10000001')
            ],
            "problem" => $problem,
            "summary" => $row['Assessment'] ?? '',
            "finding" => $finding,
            "prognosisCodeableConcept" => [
                [
                    "coding" => [
                        [
                            "system" => "http://snomed.info/sct",
                            "code" => $prognosisCode,
                            "display" => $prognosisDisplay
                        ]
                    ]
                ]
            ]
        ];

        // Add investigation only if items exist
        if (!empty($investigationItems) || !empty($row['Objective'])) {
            $payload['investigation'] = [
                [
                    "code" => [
                        "text" => $row['Objective'] ?? ''
                    ],
                    "item" => $investigationItems
                ]
            ];
        }

        return $this->sendFHIRClinicalImpression($payload);
    }
}
