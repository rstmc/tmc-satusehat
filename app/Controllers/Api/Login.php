<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\LoginorclModel;
use Firebase\JWT\JWT;

class Login extends BaseController
{
    public function index()
    {
        // PAKAI RAW INPUT (PALING STABIL)
        //$data = $this->request->getRawInput();

       // $namaUser = $data['NAMA_USER'] ?? null;
       // $passb    = $data['PASSB'] ?? null;

        // Ambil RAW JSON
        $data = $this->request->getJSON(true);

        // Fallback kalau bukan JSON
        if (!$data) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Request harus JSON'
            ]);
        }

        $namaUser = $data['NAMA_USER'] ?? null;
        $passb    = $data['PASSB'] ?? null;

        if (!$namaUser || !$passb) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'NAMA_USER dan PASSB wajib diisi'
            ]);
        }

        $model = new LoginorclModel();
        $user  = $model->login($namaUser, $passb);

        if (!$user) {
            return $this->response->setStatusCode(401)->setJSON([
                'status'  => 'error',
                'message' => 'Username atau password salah'
            ]);
        }

        // Remove password before returning
        unset($user['PASSB']);

        $payload = [
            'iss' => base_url(),
            'iat' => time(),
            'exp' => time() + (int) env('JWT_TTL'),
            'sub' => $user['ID_USER'],
            'nama' => $user['NAMA_USER'],
        ];

        $secret = (string) env('JWT_SECRET');
        if ($secret === '') {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Server misconfiguration: JWT secret not set',
            ]);
        }

        if (strlen($secret) < 32) {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Server misconfiguration: JWT secret too short, set a strong secret (min 32 bytes)',
            ]);
        }

        $token = JWT::encode($payload, $secret, 'HS256');

        return $this->response->setJSON([
            'status' => 'success',
            'source' => 'oracle',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int)getenv('JWT_TTL'),
            'data' => $user
        ]);
    }
}
