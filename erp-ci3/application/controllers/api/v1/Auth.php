<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth extends Api_Controller
{
    protected $requireAuth = false;

    public function login(): void
    {
        $this->run(function () {
            $this->requireMethod('POST');
            $this->service(Rate_limit_service::class)->hit('api-login:' . $this->context->ip, 10);
            $b = $this->body();
            $this->service(User_validator::class)->validateLogin(['identifier' => $b['identifier'] ?? ($b['username'] ?? ($b['email'] ?? '')), 'password' => $b['password'] ?? '']);
            $this->ok($this->service(Auth_service::class)->loginApi($b['identifier'] ?? ($b['username'] ?? $b['email']), $b['password']));
        });
    }

    public function refresh(): void
    {
        $this->run(function () {
            $this->requireMethod('POST');
            $this->ok($this->service(Auth_service::class)->refreshApi((string) ($this->body()['refresh_token'] ?? '')));
        });
    }

    public function me(): void
    {
        $this->run(function () {
            $auth = $this->service(Auth_service::class);
            $auth->hydrateContextFromBearer($this->input->get_request_header('Authorization') ?? '', $this->context);
            $this->ok($auth->publicUser($this->service(User_repository::class)->findOrFail($this->context->user_id)));
        });
    }
}
