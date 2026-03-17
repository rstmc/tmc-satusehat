<?php

namespace App\Controllers\Api\SatuSehat\DiagnosticReport\Laboratorium\PanelNominal;

use App\Controllers\Api\SatuSehat\DiagnosticReport\DiagnosticReportBase;

class GolonganDarahA extends DiagnosticReportBase
{
    public function push($row, $encounterId)
    {
        // Validate required fields
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        // Format dates
        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $effectiveDateTime = date('c', strtotime($dateTimeStr));
        $issued = $effectiveDateTime;

        $orgId = getenv('SATUSEHAT_ORG_ID');
        
        // Use provided ID or generate one if missing
        $diagnosticReportId = $row['DiagnosticReportId'] ?? uniqid();
        
        // Result Observation ID
        $observationId = $row['Lab_ObsID_Nominal'] ?? $row['ObservationId'] ?? '';
        
        // Specimen ID
        $specimenId = $row['Specimen_Nominal'] ?? $row['SpecimenId'] ?? '';
        
        // ServiceRequest ID
        $serviceRequestId = $row['ServiceRequest_Nominal'] ?? $row['ServiceRequestId'] ?? '';

        $payload = [
            "resourceType" => "DiagnosticReport",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/diagnostic/" . $orgId . "/lab",
                    "use" => "official",
                    "value" => $diagnosticReportId
                ]
            ],
            "status" => "final",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/v2-0074",
                            "code" => "HM",
                            "display" => "Hematology"
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
                    ]
                ]
            ],
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat']
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "effectiveDateTime" => $effectiveDateTime,
            "issued" => $issued,
            "performer" => [
                [
                    "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? '')
                ],
                [
                    "reference" => "Organization/" . $orgId
                ]
            ],
            "result" => [
                [
                    "reference" => "Observation/" . $observationId
                ]
            ],
            "specimen" => [
                [
                    "reference" => "Specimen/" . $specimenId
                ]
            ],
            "basedOn" => [
                [
                    "reference" => "ServiceRequest/" . $serviceRequestId
                ]
            ],
            "conclusionCode" => [
                [
                    "coding" => [
                        [
                            "system" => "http://loinc.org",
                            "code" => "LA19710-5",
                            "display" => "Group A"
                        ]
                    ]
                ]
            ]
        ];

        return $this->sendFHIRDiagnosticReport($payload);
    }
}
