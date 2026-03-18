<?php

namespace App\Controllers\Api\SatuSehat\ServiceRequest\Radiologi;

use App\Controllers\Api\SatuSehat\ServiceRequest\ServiceRequestBase;

class ServiceRequest extends ServiceRequestBase
{
    public function push($row, $encounterId)
    {
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $occurrenceDateTime = date('c', strtotime($dateTimeStr));
        $authoredOn = $occurrenceDateTime;

        $orgId = getenv('SATUSEHAT_ORG_ID');
        $serviceRequestId = $row['ServiceRequestId'] ?? ($row['NOTRAN'] ?? uniqid());
        $acsn = $row['ACSN'] ?? '';

        $aeTitle = 'XR0001';
        $kfaCode = $row['KFA_Code'] ?? '36572-6';

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
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "36572-6",
                        "display" => "XR thorax AP"
                    ],
                    // [
                    //     "system" => "http://terminology.kemkes.go.id/CodeSystem/kptl",
                    //     "code" => "31243.NP001.AP005",
                    //     "display" => "Radiografi Thorax 1 proyeksi (AP/PA/Lateral/Top)"
                    // ]
                ],
                "text" => "Pemeriksaan Thorax AP/PA"
            ],
            "orderDetail" => [
                [
                    "coding" => [
                        [
                            "system" => "http://dicom.nema.org/resources/ontology/DCM",
                            "code" => "DX"
                        ]
                    ],
                    "text" => "Modality Code: DX"
                ],
                [
                    "coding" => [
                        [
                            "system" => "http://sys-ids.kemkes.go.id/ae-title",
                            "display" => $aeTitle
                        ]
                    ]
                ],
                // [
                //     "coding" => [
                //         [
                //             "system" => "http://sys-ids.kemkes.go.id/kfa",
                //             "code" => $kfaCode,
                //             "display" => "Barium Sulfate"
                //         ]
                //     ]
                // ]
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
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? ''),
                "display" => $row['NmDoc'] ?? ''
            ],
            "performer" => [
                [
                    "reference" => "Practitioner/" . ($row['kdDocSatuSehatRad'] ?? ''),
                    "display" => $row['NmDocRad'] ?? ''
                ]
            ],
            "reasonCode" => [
                [
                    "coding" => [
                        [
                            "system" => "http://hl7.org/fhir/sid/icd-10",
                            "code" => (!empty($row['kdIcd']) ? $row['kdIcd'] : 'Z01.8'),
                            "display" => (!empty($row['NmIcd']) ? $row['NmIcd'] : 'Other specified special examinations')
                        ]
                    ]
                ]
            ],
            "supportingInfo" => []
        ];

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

