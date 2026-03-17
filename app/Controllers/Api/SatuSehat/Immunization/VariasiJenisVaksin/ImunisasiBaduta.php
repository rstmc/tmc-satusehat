<?php

namespace App\Controllers\Api\SatuSehat\Immunization\VariasiJenisVaksin;

use App\Controllers\Api\SatuSehat\Immunization\ImmunizationBase;

class ImunisasiBaduta extends ImmunizationBase
{
    public function push($row, $encounterId)
    {
        // IHS SatuSehat is required for the patient reference
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        $orgId = getenv('SATUSEHAT_ORG_ID');
        
        // Dates
        $occurrenceDate = isset($row['ImmunizationDate']) ? date('Y-m-d', strtotime($row['ImmunizationDate'])) : date('Y-m-d');
        $recordedDate = isset($row['RecordedDate']) ? date('Y-m-d', strtotime($row['RecordedDate'])) : date('Y-m-d');

        $payload = [
            "resourceType" => "Immunization",
            "status" => "completed",
            "vaccineCode" => [
                "coding" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/kfa",
                        "code" => $row['VaccineCodeKFA'] ?? 'VG03',
                        "display" => $row['VaccineDisplayKFA'] ?? 'MMR'
                    ],
                    [
                        "system" => "http://hl7.org/fhir/sid/cvx",
                        "code" => $row['VaccineCodeCVX'] ?? '04',
                        "display" => $row['VaccineDisplayCVX'] ?? 'M/R'
                    ]
                ]
            ],
            "patient" => [
                "reference" => "Patient/" . $row['IHSSatuSehat'],
                "display" => $row['Firstname'] ?? 'Budi Santoso'
            ],
            "occurrenceDateTime" => $occurrenceDate,
            "recorded" => $recordedDate,
            "primarySource" => false,
            "reportOrigin" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/immunization-origin",
                        "code" => "recall",
                        "display" => "Parent/Guardian/Patient Recall"
                    ]
                ]
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
                        "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? 'N10000001'),
                        "display" => $row['NmDoc'] ?? 'Dokter Bronsig'
                    ]
                ]
            ],
            "reasonCode" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.kemkes.go.id/CodeSystem/immunization-reason",
                            "code" => $row['ReasonCode'] ?? 'IM-Baduta',
                            "display" => $row['ReasonDisplay'] ?? 'Imunisasi Program Rutin Lanjutan Baduta'
                        ],
                        [
                            "system" => "http://terminology.kemkes.go.id/CodeSystem/immunization-routine-timing",
                            "code" => $row['RoutineTimingCode'] ?? 'IM-Ideal',
                            "display" => $row['RoutineTimingDisplay'] ?? 'Imunisasi Ideal'
                        ]
                    ]
                ]
            ],
            "location" => [
                "display" => $row['LocationDisplay'] ?? 'PUSKESMAS XYZ'
            ],
            "protocolApplied" => [
                [
                    "doseNumberPositiveInt" => isset($row['DoseNumber']) ? (int)$row['DoseNumber'] : 2
                ]
            ]
        ];

        return $this->sendFHIRImmunization($payload);
    }
}
