<?php

namespace App\Controllers\Api\SatuSehat\Immunization;

use App\Controllers\Api\SatuSehat\Immunization\ImmunizationBase;

class ImmunizationPush extends ImmunizationBase
{
    public function push($row, $encounterId)
    {
        if (empty($row['IHSSatuSehat'])) {
            return ['status' => 'failed', 'message' => 'No IHS SatuSehat'];
        }

        $orgId = getenv('SATUSEHAT_ORG_ID');
        $dateInput = $row['VaccinationDate'] ?? $row['TglResep'] ?? $row['RegDate'] ?? $row['Regdate'] ?? date('Y-m-d');
        $timeInput = $row['VaccinationTime'] ?? $row['RegTime'] ?? $row['Jam'] ?? date('H:i:s');
        $dateTs = strtotime($dateInput);
        if ($dateTs === false || $dateInput === '0000-00-00') {
            $dateTs = strtotime(date('Y-m-d'));
        }
        $dateStr = date('Y-m-d', $dateTs);
        $timeTs = strtotime($timeInput);
        if ($timeTs === false) {
            $timeTs = strtotime(date('H:i:s'));
        }
        $timeStr = date('H:i:s', $timeTs);
        $occTs = strtotime($dateStr . ' ' . $timeStr);
        if ($occTs === false) {
            $occTs = time();
        }
        $minTs = strtotime('2014-06-03 00:00:00');
        $nowTs = time();
        if ($occTs < $minTs) {
            $occTs = $minTs;
        }
        if ($occTs > $nowTs) {
            $occTs = $nowTs;
        }
        $occurrenceDateTime = date('c', $occTs);
        $recordedInput = $row['RecordedDate'] ?? $row['RegDate'] ?? $row['Regdate'] ?? $dateStr;
        $recTs = strtotime($recordedInput . ' ' . $timeStr);
        if ($recTs === false) {
            $recTs = $occTs;
        }
        if ($recTs < $minTs) {
            $recTs = $minTs;
        }
        if ($recTs > $nowTs) {
            $recTs = $nowTs;
        }
        $recordedDateTime = date('c', $recTs);

        $vaccineCode = $row['VaccineCode'] ?? '93001282';
        $vaccineDisplay = $row['VaccineDisplay'] ?? 'Vaksin DTP - HB - Hib 0,5 mL (PENTABIO, 1)';
        $system = $row['VaccineSystem'] ?? 'http://sys-ids.kemkes.go.id/kfa';
        $cvxCode = $row['CvxCode'] ?? '93';
        $cvxDisplay = $row['CvxDisplay'] ?? 'Vaccine';
        $lotNumber = $row['LotNumber'] ?? '202009007';
        $expirationDate = isset($row['ExpirationDate']) ? date('Y-m-d', strtotime($row['ExpirationDate'])) : date('Y-m-d', strtotime('+1 year'));
        $reasonCode = $row['ReasonCode'] ?? 'IM-Dasar';
        $reasonDisplay = $row['ReasonDisplay'] ?? 'Imunisasi Program Rutin Dasar';
        $reasonTimingCode = $row['ReasonTimingCode'] ?? 'IM-Ideal';
        $reasonTimingDisplay = $row['ReasonTimingDisplay'] ?? 'Imunisasi Ideal';
        $doseNumber = (int)($row['DoseNumber'] ?? 1);

        $payload = [
            "resourceType" => "Immunization",
            "status" => "completed",
            "vaccineCode" => [
                "coding" => [
                    [
                        "system" => $system,
                        "code" => $vaccineCode,
                        "display" => $vaccineDisplay
                    ],
                    [
                        "system" => "http://hl7.org/fhir/sid/cvx",
                        "code" => $cvxCode,
                        "display" => $cvxDisplay
                    ]
                ]
            ],
            "patient" => [
                "reference" => "Patient/" . $row['IHSSatuSehat']
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "occurrenceDateTime" => $occurrenceDateTime,
            "recorded" => $recordedDateTime,
            "primarySource" => true,
            "lotNumber" => $lotNumber,
            "expirationDate" => $expirationDate,
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
                        "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? '')
                    ]
                ]
            ],
            "location" => [
                "reference" => "Location/" . ($row['IdRuanganKemenkes'] ?? '')
            ],
            "reasonCode" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.kemkes.go.id/CodeSystem/immunization-reason",
                            "code" => $reasonCode,
                            "display" => $reasonDisplay
                        ],
                        [
                            "system" => "http://terminology.kemkes.go.id/CodeSystem/immunization-routine-timing",
                            "code" => $reasonTimingCode,
                            "display" => $reasonTimingDisplay
                        ]
                    ]
                ]
            ],
            "protocolApplied" => [
                [
                    "doseNumberPositiveInt" => $doseNumber
                ]
            ]
        ];

        return $this->sendFHIRImmunization($payload);
    }
}
