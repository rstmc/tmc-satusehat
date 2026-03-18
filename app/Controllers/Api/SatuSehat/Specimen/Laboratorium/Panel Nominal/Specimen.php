<?php

namespace App\Controllers\Api\SatuSehat\Specimen\Laboratorium\PanelNominal;

use App\Controllers\Api\SatuSehat\Specimen\SpecimenBase;

class Specimen extends SpecimenBase
{
    public function buildPayload($row, $encounterId)
    {
        // Validate required fields
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        // Format dates
        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $collectedDateTime = date('c', strtotime($dateTimeStr));
        $receivedTime = $collectedDateTime; // Using same time for received time as default

        $orgId = getenv('SATUSEHAT_ORG_ID');
        $specimenId = $row['Lab_SpecID_Nominal'] ?? uniqid();

        $payload = [
            "resourceType" => "Specimen",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/specimen/" . $orgId,
                    "value" => $specimenId,
                    "assigner" => [
                        "reference" => "Organization/" . $orgId
                    ]
                ]
            ],
            "status" => "available",
            "type" => [
                "coding" => [
                    [
                        "system" => "http://snomed.info/sct",
                        "code" => "119297000",
                        "display" => "Blood specimen"
                    ]
                ]
            ],
            "collection" => [
                "collector" => [
                    "reference" => "Practitioner/N10000005",
                    "display" => "Fatma"
                ],
                "collectedDateTime" => $collectedDateTime,
                "quantity" => [
                    "value" => 10,
                    "code" => "mL",
                    "unit" => "mL",
                    "system" => "http://unitsofmeasure.org"
                ],
                "method" => [
                    "coding" => [
                        [
                            "system" => "http://snomed.info/sct",
                            "code" => "82078001",
                            "display" => "Collection of blood specimen for laboratory"
                        ]
                    ]
                ],
                "bodySite" => [
                    "coding" => [
                        [
                            "system" => "http://snomed.info/sct",
                            "code" => "280388002",
                            "display" => "Structure of skin crease of elbow region"
                        ]
                    ]
                ],
                "fastingStatusCodeableConcept" => [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/v2-0916",
                            "code" => "NF",
                            "display" => "The patient indicated they did not fast prior to the procedure."
                        ]
                    ]
                ]
            ],
            "processing" => [
                [
                    "procedure" => [
                        "coding" => [
                            [
                                "system" => "http://snomed.info/sct",
                                "code" => "9265001",
                                "display" => "Specimen processing"
                            ]
                        ]
                    ],
                    "timeDateTime" => $collectedDateTime
                ]
            ],
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat'],
                "display" => $row['Firstname'] ?? $row['Firstname'] ?? ''
            ],
            "receivedTime" => $receivedTime
        ];

        // Add request reference if ServiceRequest_Nominal is available
        if (!empty($row['ServiceRequest_Nominal'])) {
            $reqRef = $row['ServiceRequest_Nominal'];
            if (strpos($reqRef, 'urn:uuid:') !== 0) {
                $reqRef = "ServiceRequest/" . $reqRef;
            }
            $payload['request'] = [
                [
                    "reference" => $reqRef
                ]
            ];
        }

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);
        if ($payload === null) {
            return null;
        }

        return $this->sendFHIRSpecimen($payload);
    }
}
