<?php

namespace App\Controllers\Api\SatuSehat\Procedure\Radiologi;

use App\Controllers\Api\SatuSehat\Procedure\ProcedureBase;

class StatusPuasa extends ProcedureBase
{
    public function push($row, $encounterId)
    {
        if (empty($row['IHSSatuSehat']) || empty($row['KdDocSatuSehat'])) {
            return null;
        }

        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $performedDate = date('c', strtotime($dateOnly . ' ' . $timeOnly));

        $payload = [
            "resourceType" => "Procedure",
            "status" => "not-done",
            "category" => [
                "coding" => [
                    [
                        "system" => "http://terminology.kemkes.go.id",
                        "code" => "TK000028",
                        "display" => "Diagnostic procedure"
                    ]
                ],
                "text" => "Prosedur diagnostik"
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://snomed.info/sct",
                        "code" => "792805006",
                        "display" => "Fasting"
                    ]
                ]
            ],
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat'],
                "display" => $row['Firstname'] ?? ''
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "performedPeriod" => [
                "start" => $performedDate,
                "end" => $performedDate
            ],
            "performer" => [
                [
                    "actor" => [
                        "reference" => "Practitioner/" . $row['KdDocSatuSehat'],
                        "display" => $row['NmDoc'] ?? ''
                    ]
                ]
            ],
            "note" => [
                [
                    "text" => "Tidak puasa sebelum pemeriksaan radiologi"
                ]
            ]
        ];

        return $this->sendFHIRProcedure($payload);
    }
}

