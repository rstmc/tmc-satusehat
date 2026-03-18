<?php

namespace App\Controllers\Api\SatuSehat\CarePlan\InstruksiMedikDanKeperawatanPasien;

use App\Controllers\Api\SatuSehat\CarePlan\CarePlanBase;

class InstruksiMedikDanKeperawatanPasien extends CarePlanBase
{
    public function buildPayload($row, $encounterId, $goalId = null)
    {
        $dateOnly = date('Y-m-d', strtotime($row['Regdate']));
        $timeOnly = date('H:i:s', strtotime($row['RegTime']));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $createdDate = date('c', strtotime($dateTimeStr));

        $planning = trim((string) ($row['Planning'] ?? ''));
        if ($planning === '') {
            $planning = 'Instruksi medik dan keperawatan pasien';
        }

        $payload = [
            "resourceType" => "CarePlan",
            "status" => "active",
            "intent" => "plan",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://snomed.info/sct",
                            "code" => "736271009",
                            "display" => "Outpatient care plan"
                        ]
                    ]
                ]
            ],
            "title" => "Instruksi Medik dan Keperawatan Pasien",
            "description" => $planning,
            "subject" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? '')
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "created" => $createdDate,
            "author" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? '')
            ]
        ];

        $patientName = trim((string) ($row['Firstname'] ?? ''));
        if ($patientName !== '') {
            $payload['subject']['display'] = $patientName;
        }

        $doctorName = trim((string) ($row['NmDoc'] ?? ''));
        if ($doctorName !== '') {
            $payload['author']['display'] = $doctorName;
        }

        if (!empty($goalId)) {
            $payload['goal'] = [
                [
                    "reference" => "Goal/" . $goalId
                ]
            ];
        }

        return $payload;
    }

    public function push($row, $encounterId, $goalId = null)
    {
        $dateOnly = date('Y-m-d', strtotime($row['Regdate']));
        $timeOnly = date('H:i:s', strtotime($row['RegTime']));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $createdDate = date('c', strtotime($dateTimeStr));

        $planning = trim((string) ($row['Planning'] ?? ''));
        if ($planning === '') {
            $planning = 'Instruksi medik dan keperawatan pasien';
        }

        $payload = [
            "resourceType" => "CarePlan",
            "status" => "active",
            "intent" => "plan",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://snomed.info/sct",
                            "code" => "736271009",
                            "display" => "Outpatient care plan"
                        ]
                    ]
                ]
            ],
            "title" => "Instruksi Medik dan Keperawatan Pasien",
            "description" => $planning,
            "subject" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? '')
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "created" => $createdDate,
            "author" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? '')
            ]
        ];

        $patientName = trim((string) ($row['Firstname'] ?? ''));
        if ($patientName !== '') {
            $payload['subject']['display'] = $patientName;
        }

        // Add author display if available
        $doctorName = trim((string) ($row['NmDoc'] ?? ''));
        if ($doctorName !== '') {
            $payload['author']['display'] = $doctorName;
        }

        // Add Goal if available
        if (!empty($goalId)) {
            $payload['goal'] = [
                [
                    "reference" => "Goal/" . $goalId
                ]
            ];
        }

        return $this->sendFHIRCarePlan($payload);
    }
}
