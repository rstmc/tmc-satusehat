<?php

namespace App\Controllers\Api\SatuSehat\QuestionnaireResponse;

class QuestionnaireResponse extends QuestionnaireResponseBase
{
    public function buildPayload($row, $encounterId)
    {

        $authored = isset($row['Authored']) ? date('c', strtotime($row['Authored'])) : null;

        if (!$authored) {
            $regDate = $row['RegDate'] ?? $row['Regdate'] ?? date('Y-m-d');
            $regTime = $row['RegTime'] ?? $row['Regtime'] ?? date('H:i:s');

            // Basic validation
            if (empty($regDate) || $regDate === '0000-00-00') {
                $regDate = date('Y-m-d');
            }

            $timestamp = strtotime("$regDate $regTime");
            if ($timestamp === false || $timestamp < 0) {
                $timestamp = time();
            }
            $authored = date('c', $timestamp);
        }

        $ksMapping = [
            'KPS'   => ['code' => '1', 'display' => 'Pra Sejahtera'],
            'KS-1'  => ['code' => '2', 'display' => 'Sejahtera I'],
            'KS-2'  => ['code' => '3', 'display' => 'Sejahtera II'],
            'KS-3'  => ['code' => '4', 'display' => 'Sejahtera III'],
            'KS-3+' => ['code' => '5', 'display' => 'Sejahtera III Plus'],
        ];

        $ksInput = trim($row['KeluargaSejahteraCode'] ?? 'KPS');
        $ksData = $ksMapping[$ksInput] ?? $ksMapping['KPS'];

        $payload = [
            "resourceType" => "QuestionnaireResponse",
            "questionnaire" => "https://fhir.kemkes.go.id/Questionnaire/Q0002",
            "status" => "completed",
            "subject" => [
                "reference" => "Patient/" . $row['IHSSatuSehat'],
                "display" => $row['Firstname'] ?? 'Unknown Patient'
            ],
            "encounter" => [
                "reference" => "Encounter/" . $encounterId
            ],
            "authored" => $authored,
            "author" => [
                "reference" => "Practitioner/" . ($row['KdDocSatuSehat'] ?? '')
            ],
            "source" => [
                "reference" => "Patient/" . $row['IHSSatuSehat']
            ],
            "item" => [
                [
                    "linkId" => "1",
                    "text" => "Status Kesejahteraan",
                    "answer" => [
                        [
                            "valueCoding" => [
                                "system" => "http://terminology.kemkes.go.id/CodeSystem/keluarga-sejahtera",
                                "code" => $ksData['code'],
                                "display" => $ksData['display']
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
        
        if ($payload === null) {
            return null;
        }

        return $this->sendFHIRQuestionnaireResponse($payload);
    }
}
