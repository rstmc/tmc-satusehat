<?php

namespace App\Controllers\Api\SatuSehat\Immunization\VariasiPelaporan;

use App\Controllers\Api\SatuSehat\Immunization\ImmunizationBase;

class ImunisasiDIlakukanOlehNakes extends ImmunizationBase
{
    public function push($row, $encounterId)
    {
        $orgId = getenv('SATUSEHAT_ORG_ID');
        
        $vaccineCode = $row['KfaCode'] ?? '93001282';
        $vaccineDisplay = $row['VaccineDisplay'] ?? 'Vaksin DTP - HB - Hib 0,5 mL (PENTABIO, 1)';
        
        $occurrenceDateTime = isset($row['VaccinationDate']) ? date('Y-m-d', strtotime($row['VaccinationDate'])) : date('Y-m-d');
        $recordedDate = isset($row['RecordedDate']) ? date('Y-m-d', strtotime($row['RecordedDate'])) : date('Y-m-d');

        $payload = [
            "resourceType" => "Immunization",
            "status" => "completed",
            "vaccineCode" => [
                "coding" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/kfa",
                        "code" => $vaccineCode,
                        "display" => $vaccineDisplay
                    ],
                    // Additional codes can be added here if available in $row or looked up
                    [
                        "system" => "http://hl7.org/fhir/sid/cvx",
                        "code" => $row['CvxCode'] ?? '198',
                        "display" => $row['CvxDisplay'] ?? 'DTP-hepB-Hib Pentavalent Non-US'
                    ]
                ]
            ],
            "patient" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? '100000030009'),
                "display" => $row['Firstname'] ?? 'Budi Santoso'
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "occurrenceDateTime" => $occurrenceDateTime,
            "recorded" => $recordedDate,
            "primarySource" => true,
            "location" => [
                "reference" => "Location/" . ($row['LocationId'] ?? 'ef011065-38c9-46f8-9c35-d1fe68966a3e'),
                "display" => $row['LocationDisplay'] ?? 'Ruang 1A, Poliklinik Rawat Jalan'
            ],
            "lotNumber" => $row['LotNumber'] ?? '202009007',
            "route" => [
                "coding" => [
                    [
                        "system" => "http://www.whocc.no/atc",
                        "code" => $row['RouteCode'] ?? 'inj.intramuscular',
                        "display" => $row['RouteDisplay'] ?? 'Injection Intramuscular'
                    ]
                ]
            ],
            "doseQuantity" => [
                "value" => (float)($row['DoseAmount'] ?? 1),
                "unit" => "mL",
                "system" => "http://unitsofmeasure.org",
                "code" => "ml"
            ],
            "performer" => [
                [
                    "function" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/v2-0443",
                                "code" => "AP",
                                "display" => "Administering Provider"
                            ]
                        ]
                    ],
                    "actor" => [
                        "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? 'N10000001')
                    ]
                ]
            ],
            "reasonCode" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.kemkes.go.id/CodeSystem/immunization-reason",
                            "code" => $row['ReasonCode'] ?? 'IM-Dasar',
                            "display" => $row['ReasonDisplay'] ?? 'Imunisasi Program Rutin Dasar'
                        ],
                        [
                            "system" => "http://terminology.kemkes.go.id/CodeSystem/immunization-routine-timing",
                            "code" => $row['ReasonTimingCode'] ?? 'IM-Ideal',
                            "display" => $row['ReasonTimingDisplay'] ?? 'Imunisasi Ideal'
                        ]
                    ]
                ]
            ],
            "protocolApplied" => [
                [
                    "doseNumberPositiveInt" => (int)($row['DoseNumber'] ?? 1)
                ]
            ]
        ];

        return $this->sendFHIRImmunization($payload);
    }
}
