<?php

namespace App\Controllers\Api\SatuSehat\CarePlan;

class CarePlan extends CarePlanBase
{
    public function push($row, $encounterId, $goalId = null)
    {
        // IHS SatuSehat is required for the patient reference
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        $dateInput = $row['CreatedDate'] ?? $row['Regdate'] ?? date('Y-m-d');
        $timeInput = $row['RegTime'] ?? '00:00:00';
        $dateTs = strtotime($dateInput);
        if ($dateTs === false || $dateInput === '0000-00-00') {
            $dateTs = strtotime(date('Y-m-d'));
        }
        $dateStr = date('Y-m-d', $dateTs);
        $timeTs = strtotime($timeInput);
        if ($timeTs === false) {
            $timeTs = strtotime('00:00:00');
        }
        $timeStr = date('H:i:s', $timeTs);
        $ts = strtotime($dateStr . ' ' . $timeStr);
        if ($ts === false) {
            $ts = time();
        }
        $minTs = strtotime('2014-06-03 00:00:00');
        $nowTs = time();
        if ($ts < $minTs) {
            $ts = $minTs;
        }
        if ($ts > $nowTs) {
            $ts = $nowTs;
        }
        $createdDate = date('c', $ts);

        $payload = [
            "resourceType" => "CarePlan",
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

        return $this->sendFHIRCarePlan($payload);
    }
}
