<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Base controller: builds DI container + request context, centralizes exception handling.
 * Controllers stay THIN: authorize -> validate (validator) -> call service -> render/redirect.
 */
class MY_Controller extends CI_Controller
{
    /** @var Container */
    public $container;
    /** @var Request_context */
    public $context;
    protected $layout = 'layouts/main';

    public function __construct()
    {
        parent::__construct();
        $this->container = new Container();
        $this->container->instance(CI_DB_query_builder::class, $this->db);
        $this->container->instance('CI_DB_query_builder', $this->db);
        $this->context = new Request_context();
        $this->container->instance(Request_context::class, $this->context);
        $this->container->instance(State_machine::class, new State_machine($this->config->item('erp_state_machines')));
        $this->container->instance(CI_Session::class, $this->session);
    }

    protected function service(string $class)
    {
        return $this->container->get($class);
    }

    protected function authorize(string $permission): void
    {
        if (!$this->context->can($permission)) {
            throw new Authorization_exception();
        }
    }

    protected function json($data, int $status = 200): void
    {
        $this->output->set_status_header($status)->set_content_type('application/json')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected function isAjax(): bool
    {
        return $this->input->is_ajax_request() || strpos($this->input->get_request_header('Accept') ?? '', 'application/json') !== false;
    }

    protected function requirePost(): void
    {
        if ($this->input->method() !== 'post') {
            show_error('Method Not Allowed', 405);
        }
    }

    protected function logException(Throwable $e): void
    {
        if (!($e instanceof Domain_exception) || $e->getHttpStatus() >= 500) {
            log_message('error', sprintf('[%s] %s in %s:%d', $this->context->request_id, $e->getMessage(), $e->getFile(), $e->getLine()));
        }
    }
}

/**
 * Authenticated web controller (session-based). Applies session timeout & concurrent-session control.
 */
class Web_Controller extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $auth = $this->service(Auth_service::class);
        if (!$auth->hydrateContextFromSession($this->session, $this->context)) {
            $this->session->set_flashdata('error', 'Sesi berakhir, silakan masuk kembali.');
            redirect('login');
            exit;
        }
    }

    protected function render(string $view, array $data = []): void
    {
        $data['context'] = $this->context;
        $data['title'] = $data['title'] ?? lang('app_name');
        $data['content'] = $this->load->view($view, $data, true);
        $this->load->view($this->layout, $data);
    }

    protected function flashOldInput(): void
    {
        $this->session->set_flashdata('_old_input', $this->input->post(null, false));
    }

    /**
     * Executes an action with centralized exception → user feedback mapping.
     * On success: redirect (or JSON for AJAX). On domain error: flash + redirect back.
     */
    protected function handle(callable $fn, string $successUrl, string $successMsg = ''): void
    {
        try {
            $result = $fn();
            if ($this->isAjax()) {
                $this->json(['success' => true, 'message' => $successMsg ?: lang('saved'), 'data' => $result, 'redirect' => site_url($successUrl)]);
                return;
            }
            if ($successMsg) {
                $this->session->set_flashdata('success', $successMsg);
            }
            redirect($successUrl);
        } catch (Validation_exception $e) {
            $this->logException($e);
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->getErrors()], 422);
                return;
            }
            $this->flashOldInput();
            $this->session->set_flashdata('errors', $e->getErrors());
            $this->session->set_flashdata('error', $e->getMessage());
            redirect($this->input->server('HTTP_REFERER') ?: $successUrl);
        } catch (Domain_exception $e) {
            $this->logException($e);
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => $e->getMessage()], $e->getHttpStatus());
                return;
            }
            $this->session->set_flashdata('error', $e->getMessage());
            redirect($this->input->server('HTTP_REFERER') ?: $successUrl);
        }
    }
}

/**
 * REST API controller: JWT auth, rate limit, consistent envelope, no stack trace leakage.
 */
class Api_Controller extends MY_Controller
{
    protected $requireAuth = true;

    public function __construct()
    {
        parent::__construct();
        $this->context->channel = 'api';
        try {
            $this->service(Rate_limit_service::class)->hit('api:' . $this->context->ip, (int) Env::get('API_RATE_LIMIT_PER_MINUTE', 120));
            if ($this->requireAuth) {
                $this->service(Auth_service::class)->hydrateContextFromBearer($this->input->get_request_header('Authorization') ?? '', $this->context);
            }
        } catch (Domain_exception $e) {
            $this->fail($e);
            $this->output->_display();
            exit;
        }
    }

    protected function body(): array
    {
        $raw = $this->input->raw_input_stream;
        $json = $raw ? json_decode($raw, true) : null;
        return is_array($json) ? $json : ($this->input->post(null, false) ?: []);
    }

    protected function ok($data, array $meta = [], int $status = 200): void
    {
        $this->json(['success' => true, 'data' => $data, 'meta' => $meta, 'request_id' => $this->context->request_id], $status);
    }

    protected function fail(Throwable $e): void
    {
        $this->logException($e);
        if ($e instanceof Domain_exception) {
            $this->json(['success' => false, 'error' => ['code' => strtoupper(str_replace('_exception', '', get_class($e))), 'message' => $e->getMessage(), 'details' => $e->getErrors()], 'request_id' => $this->context->request_id], $e->getHttpStatus());
            return;
        }
        $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Terjadi kesalahan internal'], 'request_id' => $this->context->request_id], 500);
    }

    protected function run(callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    protected function requireMethod(string $method): void
    {
        if ($this->input->method() !== strtolower($method)) {
            throw new Domain_exception('Method not allowed', [], 405);
        }
    }
}
