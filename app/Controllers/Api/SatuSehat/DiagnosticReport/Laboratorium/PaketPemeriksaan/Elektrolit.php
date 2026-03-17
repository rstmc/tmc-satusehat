<?php

namespace App\Controllers\Api\SatuSehat\DiagnosticReport\Laboratorium\PaketPemeriksaan;

use App\Controllers\Api\SatuSehat\DiagnosticReport\DiagnosticReportBase;

class Elektrolit extends DiagnosticReportBase
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
        
        // Specimen and ServiceRequest IDs
        $specimenId = $row['Specimen_Paket'] ?? $row['SpecimenId'] ?? '';
        $serviceRequestId = $row['ServiceRequest_Paket'] ?? $row['ServiceRequestId'] ?? '';

        // Observation IDs for Results
        $observationNatrium = $row['Observation_Natrium'] ?? '';
        $observationChloride = $row['Observation_Chloride'] ?? '';
        $observationKalium = $row['Observation_Kalium'] ?? '';

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
                            "code" => "CH",
                            "display" => "Chemistry"
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "55231-5",
                        "display" => "Electrolytes panel - Blood"
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
            "result" => [], // Populated below
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
            "conclusion" => $row['Conclusion'] ?? "Hipernatremia, Hiperkloremia, Hipokalemia" // Default or from row
        ];

        // Add Results only if IDs exist
        if (!empty($observationNatrium)) {
            $payload['result'][] = [
                "reference" => "Observation/" . $observationNatrium
            ];
        }
        if (!empty($observationChloride)) {
            $payload['result'][] = [
                "reference" => "Observation/" . $observationChloride
            ];
        }
        if (!empty($observationKalium)) {
            $payload['result'][] = [
                "reference" => "Observation/" . $observationKalium
            ];
        }

        return $this->sendFHIRDiagnosticReport($payload);
    }
}
