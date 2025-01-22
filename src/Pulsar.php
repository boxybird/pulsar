<?php

declare(strict_types=1);

namespace BoxyBird\Pulsar;

use starfederation\datastar\ServerSentEventGenerator;

final class Pulsar
{
    private static ?self $instance = null;

    private ?ServerSentEventGenerator $sse = null;

    private array $params;

    private ?string $request = null;

    private const MATCHED_RULE_PATTERN = 'pulsar/v1/handle(@[a-zA-Z_-]+)?$';

    public static function init(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        add_action('init', [$this, 'registerApiEndpoint']);
        add_filter('redirect_canonical', [$this, 'redirectCanonical'], 10, 2);
        add_action('send_headers', [$this, 'sendHeaders']);
        add_action('template_redirect', [$this, 'templateRedirect']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_footer', [$this, 'addNonceToFooter']);
    }

    public function registerApiEndpoint(): void
    {
        add_rewrite_rule(self::MATCHED_RULE_PATTERN, 'index.php', 'top');
    }

    public function redirectCanonical($redirect_url, $requested_url): string
    {
        if (!$this->isDatastarRequest()) {
            return $redirect_url;
        }

        // Remove the trailing slash from $requested_url to prevent unnecessary 301 redirect
        return rtrim($requested_url, '/');
    }

    public function sendHeaders(): void
    {
        if (!$this->isDatastarRequest()) {
            return;
        }

        $this->params = $this->getDatastarData();

        $nonce = $this->params['pulsarNonce'] ?? null;
        unset($this->params['pulsarNonce']);

        $this->validNonce($nonce);

        $this->sse = new ServerSentEventGenerator();
        $this->sse->sendHeaders();
    }

    public function templateRedirect(): void
    {
        if (!$this->isDatastarRequest()) {
            return;
        }

        $this->handleActions();
    }

    public function enqueue(): void
    {
        wp_enqueue_script_module('pulsar-datastar-script', 'https://cdn.jsdelivr.net/gh/starfederation/datastar@v1.0.0-beta.2/bundles/datastar.js', [], null, true);
    }

    public function addNonceToFooter(): void
    {
        echo '<div data-signals-pulsar-nonce="\''.wp_create_nonce('pulsar_nonce').'\'" style="display: none !important;"></div>';
    }

    private function isDatastarRequest(): bool
    {
        global $wp;

        if ($wp->matched_rule !== self::MATCHED_RULE_PATTERN || !isset($_SERVER['HTTP_DATASTAR_REQUEST'])) {
            return false;
        }

        $this->request = $wp->request;

        return true;
    }


    private function validNonce(?string $nonce = null): void
    {
        if (wp_verify_nonce($nonce, 'pulsar_nonce')) {
            return;
        }

        header('HTTP/1.1 403 Forbidden');
        exit;
    }

    private function getDatastarData(): array
    {
        $method = strtolower($_SERVER['REQUEST_METHOD']);

        $_GET['datastar'] = json_decode(stripslashes($_GET['datastar'] ?? ''), true) ?? [];

        return match ($method) {
            'get' => $_GET['datastar'],
            default => json_decode(file_get_contents('php://input') ?? '{}', true) ?? [],
        };
    }

    private function handleActions(): void
    {
        $method = strtolower($_SERVER['REQUEST_METHOD']);
        $handle = explode('@', $this->request)[1] ?? null;
        $hook_name = "pulsar/{$method}".($handle ? "@{$handle}" : '');

        do_action($hook_name, $this->sse, $this->params);

        exit;
    }
}