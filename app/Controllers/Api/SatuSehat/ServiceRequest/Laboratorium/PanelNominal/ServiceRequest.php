<?php

namespace App\Controllers\Api\SatuSehat\ServiceRequest\Laboratorium\PanelNominal;

use App\Controllers\Api\SatuSehat\ServiceRequest\ServiceRequestBase;

class ServiceRequest extends ServiceRequestBase
{
    public function push($row, $encounterId)
    {
        // Validate required fields
        if (empty($row['IHSSatuSehat']) || empty($row['KdDocSatuSehat'])) {
            return null;
        }

        // Format dates
        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $occurrenceDateTime = date('c', strtotime($dateTimeStr));
        $authoredOn = $occurrenceDateTime;

        $orgId = getenv('SATUSEHAT_ORG_ID');
        $labSrId = $row['Lab_SRID_Nominal'] ?? $row['NoOrder'] ?? uniqid();

        $payload = [
            "resourceType" => "ServiceRequest",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/servicerequest/" . $orgId,
                    "value" => $labSrId
                ]
            ],
            "status" => "active",
            "intent" => "original-order",
            "priority" => "routine",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://snomed.info/sct",
                            "code" => "108252007",
                            "display" => "Laboratory procedure"
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "57743-7",
                        "display" => "ABO group [Type] in Blood by Confirmatory method"
                    ],
                    [
                        "system" => "http://terminology.kemkes.go.id/CodeSystem/kptl",
                        "code" => "13120.JS004",
                        "display" => "Pemeriksaan golongan darah, Konfirmasi"
                    ]
                ],
                "text" => "Pemeriksaan Golongan Darah"
            ],
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat']
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "occurrenceDateTime" => $occurrenceDateTime,
            "authoredOn" => $authoredOn,
            "requester" => [
                "reference" => "Practitioner/" . $row['KdDocSatuSehat'],
                "display" => $row['NmDoc'] ?? ''
            ],
            "performer" => [
                [
                    "reference" => "Practitioner/N10000005",
                    "display" => "Fatma"
                ]
            ],
            "reasonCode" => [
                [
                    "text" => "Pemeriksaan Golongan Darah"
                ]
            ],
            "note" => [
                [
                    "text" => "Pasien tidak perlu berpuasa terlebih dahulu"
                ]
            ]
        ];

        // Add supportingInfo if Procedure_StatusPuasa_Nominal is available
        if (!empty($row['Procedure_StatusPuasa_Nominal'])) {
            $payload['supportingInfo'][] = [
                "reference" => "Procedure/" . $row['Procedure_StatusPuasa_Nominal']
            ];
        }

        return $this->sendFHIRServiceRequest($payload);
    }
}
