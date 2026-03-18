<?php

namespace App\Controllers\Api\SatuSehat\Procedure\Laboratorium\PanelNominal;

use App\Controllers\Api\SatuSehat\Procedure\ProcedureBase;

class StatusPuasa extends ProcedureBase
{
    public function buildPayload($row, $encounterId)
    {
        // Validate required fields
        if (empty($row['IHSSatuSehat']) || empty($row['KdDocSatuSehat'])) {
            return null;
        }

        // Format dates
        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $performedDate = date('c', strtotime($dateTimeStr));

        $payload = [
            "resourceType" => "Procedure",
            "status" => "not-done", // Default based on user input, logic may be needed here
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
                    "text" => "Puasa sebelum pemeriksaan Laboratorium"
                ]
            ]
        ];

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);

        if ($payload === null) {
            return null;
        }

        return $this->sendFHIRProcedure($payload);
    }
}
