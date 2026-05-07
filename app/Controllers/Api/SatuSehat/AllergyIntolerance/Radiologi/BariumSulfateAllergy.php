<?php

namespace App\Controllers\Api\SatuSehat\AllergyIntolerance\Radiologi;

use App\Controllers\Api\SatuSehat\AllergyIntolerance\AllergyIntoleranceBase;
use App\Services\SatusehatService;

class BariumSulfateAllergy extends AllergyIntoleranceBase
{
    public function __construct()
    {
        parent::__construct(new SatusehatService());
    }

    public function push($row, $encounterId)
    {
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

        $orgId = getenv('SATUSEHAT_ORG_ID');
        $identifierValue = ($row['Regno'] ?? uniqid()) . '-allergy-barium';

        $dateOnly = date('Y-m-d', strtotime($row['Regdate'] ?? date('Y-m-d')));
        $timeOnly = date('H:i:s', strtotime($row['RegTime'] ?? date('H:i:s')));
        $recordedDate = date('c', strtotime($dateOnly . ' ' . $timeOnly));

        $payload = [
            "resourceType" => "AllergyIntolerance",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/allergy/" . $orgId,
                    "use" => "official",
                    "value" => $identifierValue
                ]
            ],
            "clinicalStatus" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical",
                        "code" => "inactive",
                        "display" => "Inactive"
                    ]
                ]
            ],
            "verificationStatus" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-verification",
                        "code" => "confirmed",
                        "display" => "Confirmed"
                    ]
                ]
            ],
            "category" => [
                "medication"
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/kfa",
                        "code" => "91000928",
                        "display" => "Barium Sulfate"
                    ]
                ],
                "text" => "Alergi Barium Sulfate"
            ],
            "patient" => [
                "reference" => "Patient/" . $row['IHSSatuSehat'],
                "display" => $row['Firstname'] ?? ''
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "recordedDate" => $recordedDate,
            "recorder" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? '')
            ]
        ];

        return $this->sendFHIRAllergyIntolerance($payload);
    }
}
