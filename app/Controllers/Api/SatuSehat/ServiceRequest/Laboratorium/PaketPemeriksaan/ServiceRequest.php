<?php

namespace App\Controllers\Api\SatuSehat\ServiceRequest\Laboratorium\PaketPemeriksaan;

use App\Controllers\Api\SatuSehat\ServiceRequest\ServiceRequestBase;

class ServiceRequest extends ServiceRequestBase
{
    public function push($row, $encounterId)
    {
        // Validate required fields
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        // Format dates
        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $dateTimeStr = $dateOnly . ' ' . $timeOnly;
        $occurrenceDateTime = date('c', strtotime($dateTimeStr));
        $authoredOn = $occurrenceDateTime;

        $orgId = getenv('SATUSEHAT_ORG_ID');
        
        // Use provided ID or generate one if missing
        $serviceRequestId = $row['ServiceRequestId'] ?? uniqid();
        
        // Practitioner ID (Requester)
        $KdDocSatuSehat = $row['KdDocSatuSehat'] ?? '';
        $NmDoc = $row['NmDoc'] ?? '';

        // Procedure Status Puasa ID
        $procedureStatusPuasaId = $row['Procedure_StatusPuasa_Paket'] ?? '';

        $payload = [
            "resourceType" => "ServiceRequest",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/servicerequest/" . $orgId,
                    "value" => $serviceRequestId
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
                        "code" => "55231-5",
                        "display" => "Electrolytes panel - Blood"
                    ],
                    [
                        "system" => "http://terminology.kemkes.go.id/CodeSystem/kptl",
                        "code" => "30484",
                        "display" => "ELEKTROLIT DARAH (NA, K, CL)"
                    ]
                ],
                "text" => "Panel Elektrolit - Na, K, Cl"
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
                "reference" => "Practitioner/" . $KdDocSatuSehat,
                "display" => $NmDoc
            ],
            "performer" => [
                [
                    "reference" => "Practitioner/" . ($row['PerformerId'] ?? 'N10000005'),
                    "display" => $row['PerformerName'] ?? 'Fatma'
                ]
            ],
            "reasonCode" => [
                [
                    "text" => "Paket pemeriksaan panel elektrolit darah (Na, K, Cl)"
                ]
            ],
            "note" => [
                [
                    "text" => "Pasien tidak perlu berpuasa terlebih dahulu"
                ]
            ],
            "supportingInfo" => [
                [
                    "reference" => "Procedure/" . $procedureStatusPuasaId
                ]
            ]
        ];

        return $this->sendFHIRServiceRequest($payload);
    }
}
