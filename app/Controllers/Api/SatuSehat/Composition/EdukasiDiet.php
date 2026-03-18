<?php

namespace App\Controllers\Api\SatuSehat\Composition;

class EdukasiDiet extends CompositionBase
{
    public function buildPayload($row, $encounterId)
    {
        $orgId = getenv('SATUSEHAT_ORG_ID');

        $regDateInput = $row['RegDate'] ?? $row['Regdate'] ?? date('Y-m-d');
        $regTimeInput = $row['RegTime'] ?? $row['Regtime'] ?? $regDateInput;

        $regDate = date('Y-m-d', strtotime($regDateInput));
        $regTime = date('H:i:s', strtotime($regTimeInput));

        $compositionDate = date('c', strtotime("$regDate $regTime"));

        $identifierValue = $row['NoRawat'] ?? 'DOC-' . date('YmdHis');

        $edukasiDietText = 'Tidak butuh diet';
        if (!empty($row['TinggiBadan']) && !empty($row['BeratBadan'])) {
            $edukasiDietText = $this->getDietRecommendation($row['TinggiBadan'], $row['BeratBadan']);
        }

        $payload = [
            "resourceType" => "Composition",
            "identifier" => [
                "system" => "http://sys-ids.kemkes.go.id/composition/" . $orgId,
                "value" => $identifierValue
            ],
            "status" => "final",
            "type" => [
                "coding" => [
                    [
                        "system" => "http://loinc.org",
                        "code" => "18842-5",
                        "display" => "Discharge summary"
                    ]
                ]
            ],
            "category" => [
                [
                    "coding" => [
                        [
                            "system" => "http://loinc.org",
                            "code" => "LP173421-1",
                            "display" => "Report"
                        ]
                    ]
                ]
            ],
            "subject" => [
                "reference" => "Patient/" . ($row['IHSSatuSehat'] ?? ''),
                "display" => $row['Firstname'] ?? ''
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId,
                "display" => "Kunjungan " . ($row['Firstname'] ?? '') . " di hari " . $this->service->formatIndonesianDate($row['Regdate'])
            ],
            "date" => $compositionDate,
            "author" => [
                [
                    "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? ''),
                    "display" => $row['NmDoc'] ?? ''
                ]
            ],
            "title" => "Resume Medis Rawat Jalan",
            "custodian" => [
                "reference" => "Organization/" . $orgId
            ],
            "section" => [
                [
                    "code" => [
                        "coding" => [
                            [
                                "system" => "http://loinc.org",
                                "code" => "42344-2",
                                "display" => "Discharge diet (narrative)"
                            ]
                        ]
                    ],
                    "text" => [
                        "status" => "additional",
                        "div" => $edukasiDietText
                    ]
                ]
            ]
        ];

        return $payload;
    }

    public function push($row, $encounterId)
    {
        $payload = $this->buildPayload($row, $encounterId);
        if ($payload === null) {
            return null;
        }

        return $this->sendFHIRComposition($payload);
    }

    private function getDietRecommendation($height, $weight)
    {
        // Pastikan input berupa angka
        $height = floatval($height);
        $weight = floatval($weight);

        if ($height <= 0) {
            return 'Rekomendasi diet';
        }

        // Hitung IMT (Indeks Massa Tubuh)
        // Rumus: Berat Badan (kg) / (Tinggi Badan (m) * Tinggi Badan (m))
        $heightM = $height / 100;
        $bmi = $weight / ($heightM * $heightM);
        $bmi = round($bmi, 1);

        $recommendation = "IMT: $bmi. ";

        if ($bmi < 18.5) {
            $recommendation .= "Berat badan kurang. Disarankan diet tinggi kalori dan protein untuk mencapai berat badan ideal.";
        } elseif ($bmi >= 18.5 && $bmi <= 24.9) {
            $recommendation .= "Berat badan normal. Pertahankan pola makan sehat dan seimbang.";
        } elseif ($bmi >= 25 && $bmi <= 29.9) {
            $recommendation .= "Berat badan berlebih. Disarankan diet seimbang dengan pengurangan kalori, batasi gula dan lemak.";
        } else {
            $recommendation .= "Obesitas. Disarankan diet rendah kalori, rendah lemak, dan konsultasi dengan ahli gizi.";
        }

        return $recommendation;
    }
}
