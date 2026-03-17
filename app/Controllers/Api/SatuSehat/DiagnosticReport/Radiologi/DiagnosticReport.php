<?php

namespace App\Controllers\Api\SatuSehat\DiagnosticReport\Radiologi;

use App\Controllers\Api\SatuSehat\DiagnosticReport\DiagnosticReportBase;
use App\Services\SatusehatService;

class DiagnosticReport extends DiagnosticReportBase
{
    public function __construct()
    {
        parent::__construct(new SatusehatService());
    }

    public function push($row, $encounterId)
    {
        $orgId = getenv('SATUSEHAT_ORG_ID');
        $reportId = $row['NoTran'] ?? uniqid();

        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $effectiveDateTime = date('c', strtotime($dateTimeStr));
        $issued = $effectiveDateTime;

        $payload = [
            "resourceType" => "DiagnosticReport",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/diagnostic/" . $orgId . "/rad",
                    "use" => "official",
                    "value" => $reportId
                ]
            ],
            "status" => "final",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/v2-0074",
                            "code" => "RAD",
                            "display" => "Radiology"
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "24648-8",
                        "display" => "XR Chest PA upright"
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
                    "reference" => "Practitioner/" . ($row['PerformerRadiologi'] ?? '10012572188'),
                    "display" => $row['PerformerName'] ?? 'Dokter Radiologist'
                ],
                [
                    "reference" => "Organization/" . $orgId
                ]
            ],
            "result" => [],
            "basedOn" => [],
            "imagingStudy" => [],
            "conclusion" => $row['Kesan'] ?? ''
        ];

        if (!empty($row['ImagingStudyId'])) {
            $payload['imagingStudy'][] = [
                "reference" => "ImagingStudy/" . $row['ImagingStudyId']
            ];
        }

        if (!empty($row['ServiceRequestId'])) {
            $payload['basedOn'][] = [
                "reference" => "ServiceRequest/" . $row['ServiceRequestId']
            ];
        }

        if (!empty($row['ObservationIds']) && is_array($row['ObservationIds'])) {
            foreach ($row['ObservationIds'] as $obsId) {
                if (!empty($obsId)) {
                    $payload['result'][] = [
                        "reference" => "Observation/" . $obsId
                    ];
                }
            }
        } elseif (!empty($row['Observation_Rad'])) {
            $payload['result'][] = [
                "reference" => "Observation/" . $row['Observation_Rad']
            ];
        }

        return $this->sendFHIRDiagnosticReport($payload);
    }
}

