<?php

namespace App\Controllers\Api\SatuSehat\Medication;

class MasterMedication extends MedicationBase
{
    public function buildPayload($row, $encounterId)
    {
        // Validasi input data dari row
        $requiredFields = [
            'KodeObat' => 'Kode Obat',
            'NamaObat' => 'Nama Obat',
            'KFA' => 'KFA Code (Kode KFA)',
            'KfaBentukSediaanSystem' => 'KFA Bentuk Sediaan System',
            'KfaBentukSediaanCode' => 'KFA Bentuk Sediaan Code',
            'KfaBentukSediaanDisplay' => 'KFA Bentuk Sediaan Display',
        ];

        $missingFields = [];
        foreach ($requiredFields as $field => $label) {
            if (empty($row[$field])) {
                $missingFields[] = $label;
            }
        }

        if (!empty($missingFields)) {
            throw new \Exception("Validasi gagal, data berikut kosong: " . implode(', ', $missingFields));
        }

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
                    "value" => $row['KodeObat']
                ]
            ],
            "code" => [
                "coding" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/kfa",
                        "code" => $row['KFA'],
                        "display" => $row['NamaObat']
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
                        "system" => $row['KfaBentukSediaanSystem'],
                        "code" => $row['KfaBentukSediaanCode'],
                        "display" => $row['KfaBentukSediaanDisplay']
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

        if (!empty($row['KfaKomposisiCode'])) {
            $payload['ingredient'] = [
                [
                    "itemCodeableConcept" => [
                        "coding" => [
                            [
                                "system" => !empty($row['KfaKomposisiSystem']) ? $row['KfaKomposisiSystem'] : "http://sys-ids.kemkes.go.id/kfa",
                                "code" => $row['KfaKomposisiCode'],
                                "display" => !empty($row['KfaKomposisiDisplay']) ? $row['KfaKomposisiDisplay'] : $row['NamaObat']
                            ]
                        ]
                    ],
                    "isActive" => true,
                    "strength" => [
                        "numerator" => [
                            "value" => isset($row['KfaKadar']) && is_numeric($row['KfaKadar']) ? (float)$row['KfaKadar'] : 1,
                            "system" => "http://unitsofmeasure.org",
                            "code" => !empty($row['KfaSatuanKadar']) ? $row['KfaSatuanKadar'] : "mg"
                        ],
                        "denominator" => [
                            "value" => isset($row['KfaPembagi']) && is_numeric($row['KfaPembagi']) ? (float)$row['KfaPembagi'] : 1,
                            "system" => "http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm",
                            "code" => !empty($row['KfaSatuanBentuk']) ? $row['KfaSatuanBentuk'] : "TAB"
                        ]
                    ]
                ]
            ];
        }

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);
        $medicationId = !empty($row['Medication_id_satu_sehat']) ? $row['Medication_id_satu_sehat'] : null;
        return $this->sendFHIRMedication($payload, $medicationId);
    }
}
