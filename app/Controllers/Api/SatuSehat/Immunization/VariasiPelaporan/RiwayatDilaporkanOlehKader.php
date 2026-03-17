<?php

namespace App\Controllers\Api\SatuSehat\Immunization\VariasiPelaporan;

use App\Controllers\Api\SatuSehat\Immunization\ImmunizationBase;

class RiwayatDilaporkanOlehKader extends ImmunizationBase
{
    public function push($row, $encounterId)
    {
        $orgId = getenv('SATUSEHAT_ORG_ID');
        
        $vaccineCode = $row['KfaCode'] ?? 'VG89';
        $vaccineDisplay = $row['VaccineDisplay'] ?? 'POLIO';
        
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
                    [
                        "system" => "http://hl7.org/fhir/sid/cvx",
                        "code" => $row['CvxCode'] ?? '10',
                        "display" => $row['CvxDisplay'] ?? 'IPV'
                    ]
                ]
            ],
            "patient" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? '100000030009'),
                "display" => $row['Firstname'] ?? 'Budi Santoso'
            ],
            // Note: Encounter is NOT included in this variation as it's a historical report by a non-provider
            "occurrenceDateTime" => $occurrenceDateTime,
            "recorded" => $recordedDate,
            "primarySource" => false,
            "reportOrigin" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/immunization-origin",
                        "code" => $row['ReportOriginCode'] ?? 'provider',
                        "display" => $row['ReportOriginDisplay'] ?? 'Other Provider'
                    ]
                ]
            ],
            "performer" => [
                [
                    "function" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/v2-0443",
                                "code" => "EP",
                                "display" => "Entering Provider (probably not the same as transcriptionist?)"
                            ]
                        ],
                        "text" => $row['PerformerText'] ?? 'Kader Sri M'
                    ],
                    "actor" => [
                        "reference" => "Organization/" . $orgId
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
