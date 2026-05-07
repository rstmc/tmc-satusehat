<?php

namespace App\Controllers\Api\SatuSehat\ServiceRequest\Radiologi;

use App\Controllers\Api\SatuSehat\ServiceRequest\ServiceRequestBase;
use App\Controllers\Api\SatuSehat\RadiologiMapping;

class ServiceRequest extends ServiceRequestBase
{
    public function push($row, $encounterId)
    {
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        $mapping = RadiologiMapping::getMapping($row['KDDETAIL'] ?? ($row['ID'] ?? ''));

        if (!$mapping) {
            return [
                'status' => 'error',
                'message' => 'Mapping Radiologi tidak ditemukan untuk ID: ' . ($row['KDDETAIL'] ?? ($row['ID'] ?? '')) . '. Silahkan hubungi IT.'
            ];
        }

        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $occurrenceDateTime = date('c', strtotime($dateTimeStr));
        $authoredOn = $occurrenceDateTime;

        $orgId = getenv('SATUSEHAT_ORG_ID');
        $serviceRequestId = $row['ServiceRequestId'] ?? ($row['NOTRAN'] ?? uniqid());
        $acsn = $row['ACSN'] ?? '';

        $modality = $row['Modality'] ?? 'DX';
        $aeTitle = $row['AE_Title'] ?? null;
        $kfaCode = $mapping['kfa'] ?? ($row['KFA_KONTRAS'] ?? null);

        $payload = [
            "resourceType" => "ServiceRequest",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/servicerequest/" . $orgId,
                    "value" => $serviceRequestId
                ],
                [
                    "use" => "usual",
                    "type" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/v2-0203",
                                "code" => "ACSN"
                            ]
                        ]
                    ],
                    "system" => "http://sys-ids.kemkes.go.id/acsn/" . $orgId,
                    "value" => $acsn
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
                            "code" => "363679005",
                            "display" => "Imaging"
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
            }
            if (!empty($mapping['snomed'])) {
                $payload['code']['coding'][] = [
                    "system" => "http://snomed.info/sct",
                    "code" => $mapping['snomed'],
                    "display" => $mapping['name']
                ];
            }
            if (!empty($mapping['xcode'])) {
                $payload['code']['coding'][] = [
                    "system" => "http://terminology.kemkes.go.id/CodeSystem/kptl",
                    "code" => $mapping['xcode'],
                    "display" => $mapping['name']
                ];
            }
        }

        $payload['orderDetail'] = [
            [
                "coding" => [
                    [
                        "system" => "http://dicom.nema.org/resources/ontology/DCM",
                        "code" => $modality
                    ]
                ],
                "text" => "Modality Code: " . $modality
            ]
        ];

        if ($aeTitle) {
            $payload['orderDetail'][] = [
                "coding" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/ae-title",
                        "display" => $aeTitle
                    ]
                ]
            ];
        }

        if ($kfaCode) {
            $payload['orderDetail'][] = [
                "coding" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/kfa",
                        "code" => $kfaCode,
                        "display" => $mapping['kfa_display'] ?? "Contrast Media"
                    ]
                ]
            ];
        }

        $payload['subject'] = [
            "reference" => "Patient/" . $row['IHSSatuSehat']
        ];
        $payload['encounter'] = [
            "reference" => "Encounter/" . $encounterId
        ];
        $payload['occurrenceDateTime'] = $occurrenceDateTime;
        $payload['authoredOn'] = $authoredOn;
        $payload['requester'] = [
            "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? ''),
            "display" => $row['NmDoc'] ?? ''
        ];
        $payload['performer'] = [
            [
                "reference" => "Practitioner/" . ($row['kdDocSatuSehatRad'] ?? '10012572188'),
                "display" => $row['NmDocRad'] ?? 'Dokter Radiologist'
            ]
        ];
        $payload['reasonCode'] = [
            [
                "coding" => [
                    [
                        "system" => "http://hl7.org/fhir/sid/icd-10",
                        "code" => (!empty($row['kdIcd']) ? $row['kdIcd'] : 'A91'),
                        "display" => (!empty($row['NmIcd']) ? $row['NmIcd'] : 'Dengue haemorrhagic fever')
                    ]
                ]
            ]
        ];
        $payload['supportingInfo'] = [];

        if (!empty($row['Observation_Rad1'])) {
            $payload['supportingInfo'][] = [
                "reference" => "Observation/" . $row['Observation_Rad1']
            ];
        }
        if (!empty($row['StatusPuasaId'])) {
            $payload['supportingInfo'][] = [
                "reference" => "Procedure/" . $row['StatusPuasaId']
            ];
        }
        if (!empty($row['AllergyIntolerance_Rad'])) {
            $payload['supportingInfo'][] = [
                "reference" => "AllergyIntolerance/" . $row['AllergyIntolerance_Rad']
            ];
        }

        return $this->sendFHIRServiceRequest($payload);
    }
}

