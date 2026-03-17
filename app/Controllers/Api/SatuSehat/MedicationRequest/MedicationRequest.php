<?php

namespace App\Controllers\Api\SatuSehat\MedicationRequest;

class MedicationRequest extends MedicationRequestBase
{
    public function push($row, $encounterId)
    {
        // Organization ID from environment or config
        $orgId = getenv('SATUSEHAT_ORG_ID');

        $identifierValue = $row['NoResep'] ?? '123456788';
        $identifierItemValue = $identifierValue . '-1'; // Assuming item index 1 for simplicity, needs logic for multiple items

        $dateInput = $row['TglResep'] ?? $row['RegDate'] ?? $row['Regdate'] ?? date('Y-m-d');
        $timeInput = $row['RegTime'] ?? $row['Jam'] ?? date('H:i:s');
        $dateTs = strtotime($dateInput);
        if ($dateTs === false || $dateInput === '0000-00-00') {
            $dateTs = strtotime(date('Y-m-d'));
        }
        $dateStr = date('Y-m-d', $dateTs);
        $timeTs = strtotime($timeInput);
        if ($timeTs === false) {
            $timeTs = strtotime(date('H:i:s'));
        }
        $timeStr = date('H:i:s', $timeTs);
        $authTs = strtotime($dateStr . ' ' . $timeStr);
        if ($authTs === false) {
            $authTs = time();
        }
        $minTs = strtotime('2014-06-03 00:00:00');
        $nowTs = time();
        if ($authTs < $minTs) {
            $authTs = $minTs;
        }
        if ($authTs > $nowTs) {
            $authTs = $nowTs;
        }
        $authoredOn = date('c', $authTs);

        $payload = [
            "resourceType" => "MedicationRequest",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/prescription/" . $orgId,
                    "use" => "official",
                    "value" => $identifierValue
                ],
                [
                    "system" => "http://sys-ids.kemkes.go.id/prescription-item/" . $orgId,
                    "use" => "official",
                    "value" => $identifierItemValue
                ]
            ],
            "status" => "completed",
            "intent" => "order",
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://terminology.hl7.org/CodeSystem/medicationrequest-category",
                            "code" => "outpatient",
                            "display" => "Outpatient"
                        ]
                    ]
                ]
            ],
            "priority" => "routine",
            "medicationReference" => [
                "reference" => "Medication/" . ($row['MedicationId'] ?? '8f299a19-5887-4b8e-90a2-c2c15ecbe1d1'),
                "display" => $row['NamaObat'] ?? 'Obat Anti Tuberculosis / Rifampicin 150 mg / Isoniazid 75 mg / Pyrazinamide 400 mg / Ethambutol 275 mg Kaplet Salut Selaput (KIMIA FARMA)'
            ],
            "subject" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? ''),
                "display" => $row['Firstname'] ?? ''
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "authoredOn" => $authoredOn,
            "requester" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? ''),
                "display" => $row['NmDoc'] ?? ''
            ],
            "reasonCode" => [
                [
                    "coding" => [
                        [
                            "system" => "http://hl7.org/fhir/sid/icd-10",
                            "code" => $row['ICD10Code'] ?? 'A15.0',
                            "display" => $row['ICD10Display'] ?? 'Tuberculosis of lung, confirmed by sputum microscopy with or without culture'
                        ]
                    ]
                ]
            ],
            "courseOfTherapyType" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/medicationrequest-course-of-therapy",
                        "code" => "continuous",
                        "display" => "Continuing long term therapy"
                    ]
                ]
            ],
            "dosageInstruction" => [
                [
                    "sequence" => 1,
                    "text" => $row['AturanPakai'] ?? '4 tablet per hari',
                    "additionalInstruction" => [
                        [
                            "text" => $row['CatatanMinum'] ?? 'Diminum setiap hari'
                        ]
                    ],
                    "patientInstruction" => $row['InstruksiPasien'] ?? '4 tablet perhari, diminum setiap hari tanpa jeda sampai prose pengobatan berakhir',
                    "timing" => [
                        "repeat" => [
                            "frequency" => isset($row['Frekuensi']) ? (int)$row['Frekuensi'] : 1,
                            "period" => isset($row['Periode']) ? (int)$row['Periode'] : 1,
                            "periodUnit" => "d"
                        ]
                    ],
                    "route" => [
                        "coding" => [
                            [
                                "system" => "http://www.whocc.no/atc",
                                "code" => "O",
                                "display" => "Oral"
                            ]
                        ]
                    ],
                    "doseAndRate" => [
                        [
                            "type" => [
                                "coding" => [
                                    [
                                        "system" => "http://terminology.hl7.org/CodeSystem/dose-rate-type",
                                        "code" => "ordered",
                                        "display" => "Ordered"
                                    ]
                                ]
                            ],
                            "doseQuantity" => [
                                "value" => isset($row['Dosis']) ? (int)$row['Dosis'] : 4,
                                "unit" => "TAB",
                                "system" => "http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm",
                                "code" => "TAB"
                            ]
                        ]
                    ]
                ]
            ],
            "dispenseRequest" => [
                "dispenseInterval" => [
                    "value" => 1,
                    "unit" => "days",
                    "system" => "http://unitsofmeasure.org",
                    "code" => "d"
                ],
                "validityPeriod" => [
                    "start" => $authoredOn,
                    "end" => date('c', strtotime(date('Y-m-d\TH:i:sP', $authTs) . ' + 30 days'))
                ],
                "numberOfRepeatsAllowed" => 0,
                "quantity" => [
                    "value" => isset($row['JumlahObat']) ? (int)$row['JumlahObat'] : 120,
                    "unit" => "TAB",
                    "system" => "http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm",
                    "code" => "TAB"
                ],
                "expectedSupplyDuration" => [
                    "value" => isset($row['LamaHari']) ? (int)$row['LamaHari'] : 30,
                    "unit" => "days",
                    "system" => "http://unitsofmeasure.org",
                    "code" => "d"
                ],
                "performer" => [
                    "reference" => "Organization/" . $orgId
                ]
            ]
        ];

        return $this->sendFHIRMedicationRequest($payload);
    }
}
