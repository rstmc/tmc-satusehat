<?php namespace App\Filters;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthToken implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = $request->getServer('HTTP_AUTHORIZATION');
        if (! $auth || ! preg_match('/Bearer\s(\S+)/', $auth, $m)) {
            return service('response')->setStatusCode(401)->setJSON(['error'=>'Unauthorized']);
        }

        $token = $m[1];
        $secret = (string) env('JWT_SECRET');
        if ($secret === '') {
            return service('response')->setStatusCode(500)->setJSON(['error' => 'Server misconfiguration']);
        }

        if (strlen($secret) < 32) {
            return service('response')->setStatusCode(500)->setJSON(['error' => 'Server misconfiguration: JWT secret too short']);
        }

        try {
            $decoded = JWT::decode($token, new Key($secret,'HS256'));
            // optionally set user id: service('request')->user = $decoded->sub;
            return;
        } catch (\Throwable $e) {
            return service('response')->setStatusCode(401)->setJSON(['error'=>'Invalid token']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}