<?php

namespace App\Controllers\Api\SatuSehat\Procedure;

class Procedure extends ProcedureBase
{
    public function buildPayload($row, $encounterId)
    {
        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $startTime = date('c', strtotime($dateTimeStr));
        $endTime = date('c', strtotime($dateTimeStr) + 900);

        $payload = [
            "resourceType" => "Procedure",
            "status" => "completed",
            "category" => [
                "coding" => [
                    [
                        "system" => "http://snomed.info/sct",
                        "code" => "103693007",
                        "display" => "Diagnostic procedure"
                    ]
                ],
                "text" => "Diagnostic procedure"
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://hl7.org/fhir/sid/icd-9-cm",
                        "code" => (!empty($row['KdIcd9']) ? $row['KdIcd9'] : '87.44'),
                        "display" => (!empty($row['NmIcd9']) ? $row['NmIcd9'] : 'Routine chest X-ray, so described')
                    ]
                ]
            ],
            "subject" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? ''),
                "display" => $row['Firstname'] ?? ''
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId,
                "display" => "Tindakan " . ($row['Planning'] ?? '') . " " . ($row['Firstname'] ?? '') . " pada " . $this->service->formatIndonesianDate($row['Regdate'] ?? date('Y-m-d'))
            ],
            "performedPeriod" => [
                "start" => $startTime,
                "end" => $endTime
            ],
            "performer" => [
                [
                    "actor" => [
                        "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? ''),
                        "display" => $row['NmDoc'] ?? ''
                    ]
                ]
            ],
            "reasonCode" => [
                [
                    "coding" => [
                        [
                            "system" => "http://hl7.org/fhir/sid/icd-10",
                            "code" => (!empty($row['KdIcd']) ? $row['KdIcd'] : 'Z01.8'),
                            "display" => (!empty($row['NmIcd']) ? $row['NmIcd'] : 'Other specified special examinations')
                        ]
                    ]
                ]
            ],
            "bodySite" => [
                [
                    "coding" => [
                        [
                            "system" => "http://snomed.info/sct",
                            "code" => "302551006",
                            "display" => "Entire Thorax"
                        ]
                    ]
                ]
            ],
            "note" => [
                [
                    "text" => $row['Asesment'] ?? ""
                ]
            ]
        ];

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);
        return $this->sendFHIRProcedure($payload);
    }
}
