<?php

namespace App\Controllers\Api\SatuSehat\ServiceRequest\Laboratorium;

use App\Controllers\Api\SatuSehat\ServiceRequest\ServiceRequestBase;
use App\Services\SatusehatService;

class LaboratoriumServiceRequest extends ServiceRequestBase
{
    public function __construct()
    {
        parent::__construct(new SatusehatService());
    }

    public function buildPayload($row, $encounterId)
    {
        // Validate required fields
        if (empty($row['IHSSatuSehat']) || empty($row['KdDocSatuSehat'])) {
            return null;
        }

        // Format dates
        $dateOnly = date('Y-m-d', strtotime($row['RegDate'] ?? date('Y-m-d')));
        // If RegTime is not in row, assume 00:00 or current time? 
        // HeadBilLabTEMP has 'Jam' field.
        $timeOnly = date('H:i:s', strtotime($row['Jam'] ?? date('H:i:s')));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $occurrenceDateTime = date('c', strtotime($dateTimeStr));
        $authoredOn = $occurrenceDateTime;

        $orgId = getenv('SATUSEHAT_ORG_ID');
        $labSrId = $row['NoTran']; // Use NoTran as ID

        $loincCode = $row['LoincCode'] ?? '18719-5'; // Chemistry studies (generic example) or 26436-6 (Laboratory studies)
        $loincDisplay = $row['LoincDisplay'] ?? 'Laboratory studies';
        $textDisplay = $row['NmTarif'] ?? '';
        $reasonText = 'Paket Pemeriksaan ' . $row['NmTarif'] ?? '';
        $noteText = $row['CatatanHasil'] ?? '';

        $payload = [
            "resourceType" => "ServiceRequest",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/servicerequest/" . $orgId,
                    "value" => $labSrId
                ]
            ],
            "status" => "active",
            "intent" => "original-order",
            "priority" => "routine",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://snomed.info/sct",
                            "code" => "108252007",
                            "display" => "Laboratory procedure"
                        ]
                    ]
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => $loincCode,
                        "display" => $loincDisplay
                    ]
                ],
                "text" => $textDisplay
            ],
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat']
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "occurrenceDateTime" => $occurrenceDateTime,
            "authoredOn" => $authoredOn,
            "requester" => [
                "reference" => "Practitioner/" . $row['KdDocSatuSehat'],
                "display" => $row['NmDoc'] ?? ''
            ],
            "performer" => [
                [
                    "reference" => "Practitioner/" . ($row['KdDocSatuSehatLab'] ?? ''),
                    "display" => $row['NmDocLab'] ?? ''
                ]
            ],
            "reasonCode" => [
                [
                    "text" => $reasonText
                ]
            ],
            "note" => [
                [
                    "text" => $noteText
                ]
            ]
        ];

        if (!empty($row['Procedure_StatusPuasa_Paket'])) {
            $payload['supportingInfo'] = [
                [
                    "reference" => "Procedure/" . $row['Procedure_StatusPuasa_Paket']
                ]
            ];
        }

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);
        if ($payload === null) {
            return ['status' => 'failed', 'message' => 'Missing required fields'];
        }

        return $this->sendFHIRServiceRequest($payload);
    }
}
