<?php

namespace App\Controllers\Api\SatuSehat\Observation\Laboratorium\PaketPemeriksaan;

use App\Controllers\Api\SatuSehat\Observation\ObservationBase;

class Kalium extends ObservationBase
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
        $observationId = $row['ObservationId'] ?? uniqid();
        
        // Specimen and ServiceRequest IDs
        $specimenId = $row['Specimen_Paket'] ?? $row['SpecimenId'] ?? '';
        $serviceRequestId = $row['ServiceRequest_Paket'] ?? $row['ServiceRequestId'] ?? '';
        
        // Value and Reference Range
        $value = floatval($row['Value'] ?? 0); // Ensure float
        $low = 3.6;
        $high = 5.2; // Assumed standard upper limit
        $unit = "mmol/L";
        
        // Interpretation
        $interpretationCode = 'N';
        $interpretationDisplay = 'Normal';
        
        if ($value < $low) {
            $interpretationCode = 'L';
            $interpretationDisplay = 'Low';
        } elseif ($value > $high) {
            $interpretationCode = 'H';
            $interpretationDisplay = 'High';
        }

        // Construct Interpretation Payload
        $interpretation = [];
        if ($interpretationCode !== 'N') {
            $interpretation = [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation",
                            "code" => $interpretationCode,
                            "display" => $interpretationDisplay
                        ]
                    ]
                ]
            ];
        }

        $payload = [
            "resourceType" => "Observation",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/observation/" . $orgId,
                    "value" => $observationId
                ]
            ],
            "status" => "final",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/observation-category",
                            "code" => "laboratory",
                            "display" => "Laboratory"
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "6298-4",
                        "display" => "Potassium [Moles/volume] in Blood"
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
            "specimen" => [
                "reference" => "Specimen/" . $specimenId
            ],
            "basedOn" => [
                [
                    "reference" => "ServiceRequest/" . $serviceRequestId
                ]
            ],
            "valueQuantity" => [
                "value" => $value,
                "unit" => $unit,
                "system" => "http://unitsofmeasure.org",
                "code" => $unit
            ],
            "referenceRange" => [
                [
                    "low" => [
                        "value" => $low,
                        "unit" => $unit,
                        "system" => "http://unitsofmeasure.org",
                        "code" => $unit
                    ],
                    "high" => [
                        "value" => $high,
                        "unit" => $unit,
                        "system" => "http://unitsofmeasure.org",
                        "code" => $unit
                    ]
                ]
            ]
        ];

        // Add interpretation if not normal
        if (!empty($interpretation)) {
            $payload['interpretation'] = $interpretation;
        }

        return $this->sendFHIRObservation($payload);
    }
}
