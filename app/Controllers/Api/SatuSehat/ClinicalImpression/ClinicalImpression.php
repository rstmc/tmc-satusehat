<?php

namespace App\Controllers\Api\SatuSehat\ClinicalImpression;

use App\Controllers\Api\SatuSehat\ClinicalImpression\ClinicalImpressionBase;

class ClinicalImpression extends ClinicalImpressionBase
{
    public function buildPayload($row, $encounterId, $keluhanUtamaId = null)
    {
        $orgId = getenv('SATUSEHAT_ORG_ID');

        $regDateInput = $row['RegDate'] ?? $row['Regdate'] ?? date('Y-m-d');
        $regTimeInput = $row['RegTime'] ?? $row['Regtime'] ?? date('H:i:s');
        $regDate = date('Y-m-d', strtotime($regDateInput));
        $regTime = date('H:i:s', strtotime($regTimeInput));
        $timestamp = strtotime("$regDate $regTime") + 1800;
        $effectiveDateTime = date('c', $timestamp);
        $date = date('c', $timestamp);

        $identifierValue = $row['NoPrognosis'] ?? 'Prognosis_' . date('His');

        $prognosisCode = $row['PrognosisCode'] ?? '170968001';
        $prognosisDisplay = $row['PrognosisDisplay'] ?? 'Prognosis good';

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

        $problem = [];
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

        return $payload;
    }

    public function push($row, $encounterId, $keluhanUtamaId = null)
    {
        $payload = $this->buildPayload($row, $encounterId, $keluhanUtamaId);
        if ($payload === null) {
            return null;
        }

        return $this->sendFHIRClinicalImpression($payload);
    }
}
