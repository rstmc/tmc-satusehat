<?php

namespace App\Controllers\Api\SatuSehat\DiagnosticReport\Radiologi;

use App\Controllers\Api\SatuSehat\DiagnosticReport\DiagnosticReportBase;
use App\Services\SatusehatService;
use App\Controllers\Api\SatuSehat\RadiologiMapping;

class DiagnosticReport extends DiagnosticReportBase
{
    public function __construct()
    {
        parent::__construct(new SatusehatService());
    }

    public function push($row, $encounterId)
    {
        if (empty($row['IHSSatuSehat'])) {
            return [
                'status' => 'error',
                'message' => 'IHS Patient ID tidak ditemukan.'
            ];
        }

        $mapping = RadiologiMapping::getMapping($row['KDDETAIL'] ?? ($row['ID'] ?? ''));

        if (!$mapping) {
            return [
                'status' => 'error',
                'message' => 'Mapping Radiologi tidak ditemukan untuk ID: ' . ($row['KDDETAIL'] ?? ($row['ID'] ?? '')) . '. Silahkan hubungi IT.'
            ];
        }

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
                "coding" => [],
                "text" => $mapping['name'] ?? ($row['NmTindakan'] ?? 'Pemeriksaan Radiologi')
            ],
        ];

        if ($mapping) {
            if (!empty($mapping['loinc'])) {
                $payload['code']['coding'][] = [
                    "system" => "http://loinc.org",
                    "code" => $mapping['loinc'],
                    "display" => $mapping['name']
                ];
            } elseif (!empty($mapping['snomed'])) {
                $payload['code']['coding'][] = [
                    "system" => "http://snomed.info/sct",
                    "code" => $mapping['snomed'],
                    "display" => $mapping['name']
                ];
            } elseif (!empty($mapping['xcode'])) {
                $payload['code']['coding'][] = [
                    "system" => "http://terminology.kemkes.go.id/CodeSystem/kptl",
                    "code" => $mapping['xcode'],
                    "display" => $mapping['name']
                ];
            }
        }

        $payload["subject"] = [
            "reference" => "Patient/" . $row['IHSSatuSehat']
        ];
        $payload["encounter"] = [
            "reference" => "Encounter/" . $encounterId
        ];
        $payload["effectiveDateTime"] = $effectiveDateTime;
        $payload["issued"] = $issued;
        $payload["performer"] = [
            [
                "reference" => "Practitioner/" . ($row['PerformerRadiologi'] ?? '10012572188'),
                "display" => $row['PerformerName'] ?? 'Dokter Radiologist'
            ],
            [
                "reference" => "Organization/" . $orgId
            ]
        ];
        $payload["result"] = [];
        $payload["basedOn"] = [];
        $payload["imagingStudy"] = [];
        $payload["conclusion"] = $row['Kesan'] ?? '';

        if (!empty($row['ImagingStudyId'])) {
            $payload['imagingStudy'][] = [
                "reference" => "ImagingStudy/" . $row['ImagingStudyId']
            ];
        }

        if (!empty($row['ServiceRequest_Rad'])) {
            $payload['basedOn'][] = [
                "reference" => "ServiceRequest/" . $row['ServiceRequest_Rad']
            ];
        } elseif (!empty($row['ServiceRequestId'])) {
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

