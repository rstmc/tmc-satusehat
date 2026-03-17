<?php

namespace App\Controllers\Api\SatuSehat\Procedure;

class Procedure extends ProcedureBase
{
    public function push($row, $encounterId)
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
                        "code" => $row['KdIcd9'] ?? '87.44',
                        "display" => $row['NmIcd9'] ?? 'Routine chest x-ray, so described'
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
                            "code" => $row['KdIcd'] ?? '',
                            "display" => $row['NmIcd'] ?? ''
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

        return $this->sendFHIRProcedure($payload);
    }
}
