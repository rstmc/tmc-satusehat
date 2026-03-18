<?php

namespace App\Controllers\Api\SatuSehat\EpisodeOfCare;

use App\Controllers\Api\SatuSehat\EpisodeOfCare\EpisodeOfCareBase;

class EpisodeOfCare extends EpisodeOfCareBase
{
    public function buildPayload($row, $encounterId, $keluhanUtamaId = null)
    {
        // IHS SatuSehat is required for the patient reference
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        $orgId = getenv('SATUSEHAT_ORG_ID');
        $episodeId = $row['EpisodeOfCareId'] ?? '';
        
        // Normalize dates
        $regDateInput = $row['RegDate'] ?? $row['Regdate'] ?? date('Y-m-d');
        $regTimeInput = $row['RegTime'] ?? $row['Regtime'] ?? '00:00:00';
        
        $regDate = date('Y-m-d', strtotime($regDateInput));
        $regTime = date('H:i:s', strtotime($regTimeInput));
        
        $startDateTime = strtotime("$regDate $regTime");
        $startDate = date('c', $startDateTime);
        // $endDate = date('c', $startDateTime + 3600); // Assume 1 hour duration if same

        $payload = [
            "resourceType" => "EpisodeOfCare",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/episode-of-care/" . $orgId,
                    "value" => $episodeId
                ]
            ],
            "status" => 'active',
            "statusHistory" => [
                [
                    "status" => "active",
                    "period" => [
                        "start" => $startDate
                    ]
                ]
            ],
            "type" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/episodeofcare-type",
                            "code" => "hacc",
                            "display" => "Home and Community Care"
                        ]
                    ]
                ]
            ],
            "patient" => [
                "reference" => "Patient/" . $row['IHSSatuSehat'],
                "display" => $row['Firstname'] ?? ''
            ],
            "managingOrganization" => [
                "reference" => "Organization/" . $orgId
            ],
            "period" => [
                "start" => $startDate
            ],
            "careManager" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? ''),
                "display" => $row['NmDoc'] ?? ''
            ]
        ];

        if (!empty($keluhanUtamaId)) {
            $conditionRef = $keluhanUtamaId;
            if (strpos($conditionRef, 'urn:uuid:') !== 0) {
                $conditionRef = "Condition/" . $conditionRef;
            }
            $payload["diagnosis"] = [
                [
                    "condition" => [
                        "reference" => $conditionRef,
                        "display" => $row['Subjective'] ?? ''
                    ],
                    "role" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/diagnosis-role",
                                "code" => "CC",
                                "display" => "Chief Complaint"
                            ]
                        ]
                    ],
                    "rank" => 1
                ]
            ];
        }

        return $payload;
    }

    public function push($row, $encounterId, $keluhanUtamaId = null)
    {
        $payload = $this->buildPayload($row, $encounterId, $keluhanUtamaId);
        
        if ($payload === null) {
            return null;
        }

        return $this->sendFHIREpisodeOfCare($payload);
    }
}
