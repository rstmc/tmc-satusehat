<?php

namespace App\Controllers\Api\SatuSehat\CarePlan;

class CarePlan extends CarePlanBase
{
    public function buildPayload($row, $encounterId, $goalId = null)
    {
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        $createdDate = $this->service->sanitizeFhirDateTime(
            $row['CreatedDate'] ?? $row['Regdate'] ?? null,
            $row['RegTime'] ?? null
        );

        $orgId = getenv('SATUSEHAT_ORG_ID');

        $payload = [
            "resourceType" => "CarePlan",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/careplan/" . $orgId,
                    "use"    => "official",
                    "value"  => ($row['Regno'] ?? '') . '-care_plan'
                ]
            ],
            "status" => $row['Status'] ?? 'active',
            "intent" => $row['Intent'] ?? 'plan',
            "description" => $row['Planning'] ?? 'Rujuk ke RS Rujukan Tumbuh Kembang level 1',
            "title" => $row['Title'] ?? "Rencana Rawat Pasien",
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
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat'],
                "display" => $row['Firstname'] ?? 'Anak Smith'
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "created" => $createdDate,
            "author" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? 'N10000001')
            ]
        ];

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
