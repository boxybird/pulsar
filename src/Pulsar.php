<?php

declare(strict_types=1);

namespace BoxyBird\Pulsar;

use DOMDocument;
use Exception;
use Illuminate\Encryption\Encrypter;

final class Pulsar
{
    private const MATCHED_RULE_PATTERN = 'pulsar/v1/([a-zA-Z_-]+)?$';

    private const NONCE_NAME = 'pulsar_nonce';

    private static ?self $instance = null;

    private ?ServerSentEventGenerator $sse = null;

    private ?string $request = null;

    private ?array $pulsarRequestData = null;

    private ?string $template_name = null;

    private ?Encrypter $encrypter = null;

    public function __construct()
    {
        $this->setEncrypter();

        add_action('init', [$this, 'registerApiEndpoint']);
        add_filter('redirect_canonical', [$this, 'redirectCanonical'], 10, 2);
        add_action('send_headers', [$this, 'sendHeaders']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('template_include', [$this, 'templateInclude']);
    }

    private function setEncrypter(): void
    {
        if (!defined('PULSAR_ENCRYPTION_KEY')) {
            throw new Exception(
                __CLASS__.' cannot find constant PULSAR_ENCRYPTION_KEY. Must be set in wp-config.php as 16 character random string.'
            );
        }

        $this->encrypter = new Encrypter(PULSAR_ENCRYPTION_KEY);
    }

    public static function init(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
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

    private function isDatastarRequest(): bool
    {
        global $wp;

        if ($wp->matched_rule !== self::MATCHED_RULE_PATTERN || !isset($_SERVER['HTTP_DATASTAR_REQUEST'])) {
            return false;
        }

        $this->request = $wp->request;

        return true;
    }

    public function sendHeaders(): void
    {
        if (!$this->isDatastarRequest()) {
            return;
        }

        try {
            $data = json_decode(stripslashes_deep($_SERVER['HTTP_PULSAR_DATA']), true)['data'] ?? null;
            $this->pulsarRequestData = $this->encrypter->decrypt($data);
        } catch (Exception) {
            header('HTTP/1.1 400 Bad Request');
            exit;
        }

        $this->validNonce();

        $this->sse = new ServerSentEventGenerator();
        $this->sse->headers();
    }

    private function validNonce(): void
    {
        if (wp_verify_nonce($this->pulsarRequestData['nonce'] ?? -1, self::NONCE_NAME)) {
            return;
        }

        header('HTTP/1.1 403 Forbidden');
        exit;
    }

    public function templateInclude($template): ?string
    {
        if (!$this->isDatastarRequest()) {
            $this->template_name = basename($template);

            return $template;
        }

        $method = strtolower($_SERVER['REQUEST_METHOD']);
        $handle = explode('/', $this->request)[2] ?? null;

        if ($handle === 'template') {
            $this->handleTemplateActions();
        } else {
            $this->handleUserActions($method, $handle);
        }

        return null;
    }

    public function enqueue(): void
    {
        wp_enqueue_script('pulsar-script', PULSAR_URL.'js/pulsar.js', [], null, true);
        wp_enqueue_script_module('pulsar-datastar-script', PULSAR_URL.'js/datastar.js', ['pulsar-script'], null, true);

        $data = [
            'nonce' => wp_create_nonce(self::NONCE_NAME),
            'postId' => get_the_ID(),
            'template' => $this->template_name ? str_replace('.php', '', $this->template_name) : '',
        ];

        wp_localize_script('pulsar-script', 'pulsarData', [
            'data' => $this->encrypter->encrypt($data),
        ]);
    }

    private function handleUserActions(string $method, string $handle): void
    {
        $hook_name = "pulsar/{$method}/{$handle}";

        do_action($hook_name, $this->sse, $this->sse::readSignals());
        exit;
    }

    private function handleTemplateActions()
    {
        global $post;

        // Set the global $post to the $post that the component is being loaded on.
        // Normally this info in lost when the component is rendered via AJAX.
        $post = get_post((int) $this->pulsarRequestData['postId']);

        // Same as above, but for $wp_query->setup_postdata( $post )
        setup_postdata($post);

        // Sanitize the template name to prevent directory traversal
        $template = basename($this->pulsarRequestData['template']);

        // Ensure $template contains only valid characters (alphanumeric, underscores, and dashes)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $template)) {
            header('HTTP/1.1 400 Bad Request');
            exit;
        }

        ob_start();
        include locate_template($template.'.php');
        $content = ob_get_clean();

        $signals = $this->sse::readSignals();
        $includes = $signals['pulsar']['includes'] ?? [];

        $dom = new DOMDocument();
        // Suppress warnings from malformed HTML
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $fragments = [];

        foreach ($includes as $include) {
            $include = str_replace('#', '', $include);
            $include = $dom->getElementById($include);

            $fragments[] = $dom->saveHTML($include);
        }

        $fragments[] = $dom->saveHTML($dom->getElementById('pulsar-root'));

        $this->sse->mergeFragments(implode('', $fragments), [
            'useViewTransition' => true,
        ]);

        $this->sse->mergeSignals($signals);

        wp_reset_postdata();
        exit;
    }
}