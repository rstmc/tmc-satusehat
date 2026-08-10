<?php

namespace App\Controllers\Api\SatuSehat\MedicationRequest;

class MedicationRequest extends MedicationRequestBase
{
    public function buildPayload($row, $encounterId)
    {
        // Organization ID from environment or config
        $orgId = env('SATUSEHAT_ORG_ID') ?: getenv('SATUSEHAT_ORG_ID') ?: ($_ENV['SATUSEHAT_ORG_ID'] ?? '');

        $identifierValue = !empty($row['NoResep']) ? $row['NoResep'] : (!empty($row['BLCode']) ? $row['BLCode'] : ($row['Regno'] ?? 'UNKNOWN'));
        $urutan = $row['Urutan'] ?? $row['KodeObat'] ?? '1';
        if (strpos($urutan, '-') !== false) {
            $parts = explode('-', $urutan);
            $urutan = $parts[0];
        }
        $identifierItemValue = $identifierValue . '-' . $urutan;

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

        $medRef = $row['MedicationId'] ?? '8f299a19-5887-4b8e-90a2-c2c15ecbe1d1';
        if (strpos($medRef, 'urn:uuid:') !== 0) {
            $medRef = "Medication/" . $medRef;
        }

        $satuanRaw = $row['Satuan'] ?? $row['SatuanObat'] ?? 'TAB';
        $sUpper = strtoupper(trim($satuanRaw));
        $mapping = [
            'TABLET' => 'TAB',
            'TAB' => 'TAB',
            'CAPSULE' => 'CAP',
            'CAPSUL' => 'CAP',
            'KAPSUL' => 'CAP',
            'CAP' => 'CAP',
            'BOTOL' => 'ORALSOL',
            'BOTTLE' => 'ORALSOL',
            'BOT' => 'ORALSOL',
            'AMPUL' => 'TAB',
            'AMP' => 'TAB',
            'VIAL' => 'TAB',
            'BOX' => 'TAB',
            'PC' => 'TAB',
            'PCS' => 'TAB',
            'PIECE' => 'TAB',
            'SACHET' => 'TAB',
            'SUPP' => 'SUPP',
            'SYRUP' => 'SYR',
            'SIRUP' => 'SYR'
        ];
        $drugFormCode = $mapping[$sUpper] ?? 'TAB';

        $qtyRaw = !empty($row['Qty']) ? $row['Qty'] : (!empty($row['JumlahObat']) ? $row['JumlahObat'] : 1);
        $qtyVal = floatval($qtyRaw);
        if ($qtyVal <= 0) {
            $qtyVal = abs($qtyVal) ?: 1;
        }
        // dispenseRequest.quantity harus integer — SatuSehat RuleNumber 10347
        $qtyInt = (int)round($qtyVal);
        if ($qtyInt <= 0) $qtyInt = 1;

        $durationRaw = !empty($row['Duration']) ? $row['Duration'] : (!empty($row['JumlahHari']) ? $row['JumlahHari'] : 1);
        $durationVal = floatval($durationRaw);
        if ($durationVal <= 0) {
            $durationVal = abs($durationVal) ?: 1;
        }
        // dispenseRequest.expectedSupplyDuration harus integer — SatuSehat RuleNumber 10347
        $durationInt = (int)round($durationVal);
        if ($durationInt <= 0) $durationInt = 1;

        // Tentukan category berdasarkan jenis kunjungan (sama dengan MedicationDispense)
        // - RJ bukan poli 30 → outpatient
        // - IGD (KdPoli=30) atau Rawat Inap → cek ObatPulang: 1=discharge, 0=inpatient
        $kdTuju      = strtoupper(trim($row['KdTuju'] ?? 'RJ'));
        $kdPoli      = trim($row['KdPoli'] ?? '');
        $isIGD       = ($kdPoli === '30');
        $isRawatInap = ($kdTuju !== 'RJ');

        if (!$isIGD && !$isRawatInap) {
            $requestCategory     = 'outpatient';
            $requestCategoryDisp = 'Outpatient';
        } else {
            $obatPulang = !empty($row['ObatPulang']) && $row['ObatPulang'] == 1;
            if ($obatPulang) {
                $requestCategory     = 'discharge';
                $requestCategoryDisp = 'Discharge';
            } else {
                $requestCategory     = 'inpatient';
                $requestCategoryDisp = 'Inpatient';
            }
        }

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
                            "code" => $requestCategory,
                            "display" => $requestCategoryDisp
                        ]
                    ]
                ]
            ],
            "priority" => "routine",
            "medicationReference" => [
                "reference" => $medRef,
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
            // reasonCode: opsional — hanya kirim jika ICD-10 tersedia dari data pasien
            // Tidak hardcode fallback TB, karena resep bukan selalu untuk TB

            "dosageInstruction" => [
                [
                    "sequence" => 1,
                    // AturanPakai = instruksi dokter (misal: "3x1"), fallback ke kombinasi Signa1xSigna2
                    "text" => !empty($row['AturanPakai'])
                        ? $row['AturanPakai']
                        : ((!empty($row['Signa1']) && !empty($row['Signa2']))
                            ? $row['Signa1'] . 'x' . $row['Signa2']
                            : 'Sesuai petunjuk dokter'),
                    // patientInstruction: KeteranganPakai atau NoteCaraMinumObat (opsional)
                    "patientInstruction" => trim(implode('. ', array_filter([
                        $row['KeteranganPakai'] ?? '',
                        $row['NoteCaraMinumObat'] ?? '',
                    ]))) ?: null,
                    "timing" => [
                        "repeat" => [
                            // Signa1 = berapa kali sehari (harus integer >= 1)
                            "frequency" => !empty($row['Signa1']) && is_numeric($row['Signa1']) && (int)$row['Signa1'] >= 1
                                ? (int)$row['Signa1']
                                : 1,
                            "period" => 1,
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
                                "value" => $qtyVal,
                                "unit" => $drugFormCode,
                                "system" => "http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm",
                                "code" => $drugFormCode
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
                    "end" => date('c', $authTs + (30 * 24 * 3600)) // Valid for 30 days
                ],
                "numberOfRepeatsAllowed" => 0,
                "quantity" => [
                    "value" => $qtyInt,
                    "unit" => $drugFormCode,
                    "system" => "http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm",
                    "code" => $drugFormCode
                ],
                "expectedSupplyDuration" => [
                    "value" => $durationInt,
                    "unit" => "days",
                    "system" => "http://unitsofmeasure.org",
                    "code" => "d"
                ],
                "performer" => [
                    "reference" => "Organization/" . $orgId
                ]
            ]
        ];

        // Hapus patientInstruction jika null (opsional, tidak perlu dikirim jika kosong)
        if (isset($payload['dosageInstruction'][0]['patientInstruction']) 
            && $payload['dosageInstruction'][0]['patientInstruction'] === null) {
            unset($payload['dosageInstruction'][0]['patientInstruction']);
        }

        // reasonCode: tambahkan hanya jika ICD-10 pasien tersedia (dari data register)
        $icd10Code = trim($row['KdDiag'] ?? $row['ICD10Code'] ?? $row['KdPenyakit'] ?? '');
        $icd10Display = trim($row['NmDiag'] ?? $row['ICD10Display'] ?? $row['NmPenyakit'] ?? '');
        if (!empty($icd10Code)) {
            $payload['reasonCode'] = [
                [
                    "coding" => [
                        [
                            "system" => "http://hl7.org/fhir/sid/icd-10",
                            "code" => $icd10Code,
                            "display" => $icd10Display ?: $icd10Code,
                        ]
                    ]
                ]
            ];
        }

        // courseOfTherapyType: hanya untuk obat kronis (dari tabel HeadApotikKronis/DetailApotikKronis)
        // IsObatKronis = 1 jika data berasal dari HeadApotikKronis, 0 jika dari HeadApotik biasa
        if (!empty($row['IsObatKronis']) && $row['IsObatKronis'] == 1) {
            $payload['courseOfTherapyType'] = [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/medicationrequest-course-of-therapy",
                        "code" => "continuous",
                        "display" => "Continuous long term therapy"
                    ]
                ]
            ];
        }

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);
        return $this->sendFHIRMedicationRequest($payload);
    }
}
