<?php

namespace App\Controllers\Api\SatuSehat\QuestionnaireResponse;

class QuestionnaireResponse extends QuestionnaireResponseBase
{
    public function push($row, $encounterId)
    {
        if (empty($row['IHSSatuSehat'])) {
            return null;
        }

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
                                "system" => "http://terminology.kemkes.go.id",
                                "code" => $row['KeluargaSejahteraCode'] ?? 'KPS',
                                "display" => $row['KeluargaSejahteraDisplay'] ?? 'Keluarga Pra Sejahtera (KPS)'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $this->sendFHIRQuestionnaireResponse($payload);
    }
}
