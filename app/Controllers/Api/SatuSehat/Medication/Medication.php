<?php

namespace App\Controllers\Api\SatuSehat\Medication;

class Medication extends MedicationBase
{
    public function buildPayload($row, $encounterId)
    {
        // Organization ID from environment or config
        $orgId = getenv('SATUSEHAT_ORG_ID');

        $payload = [
            "resourceType" => "Medication",
            "meta" => [
                "profile" => [
                    "https://fhir.kemkes.go.id/r4/StructureDefinition/Medication"
                ]
            ],
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/medication/" . $orgId,
                    "use" => "official",
                    "value" => $row['KodeObat'] ?? ''
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/kfa",
                        "code" => (!empty($row['KodeObat']) ? $row['KodeObat'] : "93001019"),
                        "display" => (!empty($row['NamaObat']) ? $row['NamaObat'] : "Obat Tambahan")
                    ]
                ]
            ],
            "status" => "active",
            "manufacturer" => [
                "reference" => "Organization/" . ($orgId)
            ],
            "form" => [
                "coding" => [
                    [
                        "system" => "http://terminology.kemkes.go.id/CodeSystem/medication-form",
                        "code" => "BS023",
                        "display" => "Kaplet Salut Selaput"
                    ]
                ]
            ],
            "extension" => [
                [
                    "url" => "https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType",
                    "valueCodeableConcept" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.kemkes.go.id/CodeSystem/medication-type",
                                "code" => "NC",
                                "display" => "Non-compound"
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);
        return $this->sendFHIRMedication($payload);
    }
}
