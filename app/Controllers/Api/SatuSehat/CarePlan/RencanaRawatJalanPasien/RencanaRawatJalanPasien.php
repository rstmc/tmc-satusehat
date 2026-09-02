<?php

namespace App\Controllers\Api\SatuSehat\CarePlan\RencanaRawatJalanPasien;

use App\Controllers\Api\SatuSehat\CarePlan\CarePlanBase;

class RencanaRawatJalanPasien extends CarePlanBase
{
    public function buildPayload($row, $encounterId, $goalId = null)
    {
        $createdDate = $this->service->sanitizeFhirDateTime($row['Regdate'] ?? null, $row['RegTime'] ?? null);

        $planning = trim((string) ($row['Planning'] ?? ''));
        if ($planning === '') {
            $planning = 'Rencana rawat pasien';
        }

        $orgId = env('SATUSEHAT_ORG_ID') ?: getenv('SATUSEHAT_ORG_ID') ?: ($_ENV['SATUSEHAT_ORG_ID'] ?? '');

        $payload = [
            "resourceType" => "CarePlan",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/careplan/" . $orgId,
                    "use"    => "official",
                    "value"  => ($row['Regno'] ?? '') . '-rencana_rawat_jalan_pasien'
                ]
            ],
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
            "title" => "Rencana Rawat Pasien",
            "description" => $planning,
            "subject" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? ''),
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
        $payload = $this->buildPayload($row, $encounterId, $goalId);
        if ($payload === null) {
            return null;
        }

        return $this->sendFHIRCarePlan($payload);
    }
}
