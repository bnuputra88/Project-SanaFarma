<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth extends MY_Controller
{
    public function login(): void
    {
        if ($this->session->userdata('user_id')) {
            redirect('dashboard');
        }
        if ($this->input->method() === 'post') {
            try {
                $this->service(Rate_limit_service::class)->hit('login:' . $this->context->ip, 20);
                $this->service(User_validator::class)->validateLogin($this->input->post(null, false));
                $user = $this->service(Auth_service::class)->loginWeb($this->session, (string) $this->input->post('identifier'), (string) $this->input->post('password'));
                redirect($user['must_change_password'] ? 'profile/password' : 'dashboard');
                return;
            } catch (Domain_exception $e) {
                $this->session->set_flashdata('error', $e->getMessage());
                redirect('login');
                return;
            }
        }
        $this->load->view('auth/login', ['title' => lang('login_title')]);
    }

    public function logout(): void
    {
        $auth = $this->service(Auth_service::class);
        $auth->hydrateContextFromSession($this->session, $this->context);
        $auth->logoutWeb($this->session);
        redirect('login');
    }

    public function forgot(): void
    {
        if ($this->input->method() === 'post') {
            $this->service(Rate_limit_service::class)->hit('forgot:' . $this->context->ip, 5);
            $token = $this->service(Auth_service::class)->createResetToken((string) $this->input->post('email'));
            // Email delivery is Phase 7 (notification engine); token is logged for admin-assisted reset.
            if ($token) {
                log_message('info', 'Password reset link: ' . site_url('password/reset/' . $token));
            }
            $this->session->set_flashdata('success', 'Jika email terdaftar, instruksi reset telah dikirim.');
            redirect('login');
            return;
        }
        $this->load->view('auth/forgot', ['title' => 'Lupa Password']);
    }

    public function reset(string $token): void
    {
        if ($this->input->method() === 'post') {
            try {
                if ($this->input->post('password') !== $this->input->post('password_confirm')) {
                    throw new Validation_exception(['password_confirm' => 'Konfirmasi password tidak sama']);
                }
                $this->service(Auth_service::class)->resetPassword($token, (string) $this->input->post('password'));
                $this->session->set_flashdata('success', 'Password berhasil direset. Silakan masuk.');
                redirect('login');
                return;
            } catch (Domain_exception $e) {
                $this->session->set_flashdata('error', $e->getMessage() . ' ' . implode(' ', $e->getErrors()));
            }
        }
        $this->load->view('auth/reset', ['title' => 'Reset Password', 'token' => $token]);
    }

    public function change_password(): void
    {
        $auth = $this->service(Auth_service::class);
        if (!$auth->hydrateContextFromSession($this->session, $this->context)) {
            redirect('login');
            return;
        }
        if ($this->input->method() === 'post') {
            try {
                $d = $this->input->post(null, false);
                $this->service(User_validator::class)->validatePasswordChange($d);
                $auth->changePassword($this->context->user_id, $d['current_password'], $d['password']);
                $this->session->set_flashdata('success', 'Password berhasil diubah');
                redirect('dashboard');
                return;
            } catch (Domain_exception $e) {
                $this->session->set_flashdata('error', $e->getMessage() . ' ' . implode(' ', $e->getErrors()));
            }
        }
        $data = ['title' => 'Ganti Password', 'context' => $this->context];
        $data['content'] = $this->load->view('auth/change_password', $data, true);
        $this->load->view('layouts/main', $data);
    }
}
