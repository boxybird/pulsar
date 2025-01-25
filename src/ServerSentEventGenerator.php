<?php

namespace BoxyBird\Pulsar;

use starfederation\datastar\Consts;
use starfederation\datastar\enums\FragmentMergeMode;
use starfederation\datastar\events\MergeFragments;
use starfederation\datastar\ServerSentEventGenerator as Generator;

class ServerSentEventGenerator extends Generator
{

    /**
     * Returns the signals sent in the incoming request.
     */
    public static function readSignals(): array
    {
        // Original code
        // $input = $_GET[Consts::DATASTAR_KEY] ?? file_get_contents('php://input');

        // Remove slashed added by wp_magic_quotes()
        $input = isset($_GET[Consts::DATASTAR_KEY])
            ? stripslashes_deep($_GET[Consts::DATASTAR_KEY])
            : file_get_contents('php://input');

        return $input ? json_decode($input, true) : [];
    }

    /**
     * Merges HTML fragments into the DOM.
     *
     * @param  array{
     *     selector?: string|null,
     *     mergeMode?: FragmentMergeMode|string|null,
     *     settleDuration?: int|null,
     *     useViewTransition?: bool|null,
     *     eventId?: string|null,
     *     retryDuration?: int|null,
     * }  $options
     */
    public function mergeFragmentsFromFile(string $file, array $options = []): void
    {
        $this->sendEvent(new MergeFragments($this->renderFile($file), $options));
    }

    protected function renderFile(string $file): string
    {
        if (!file_exists($file)) {
            throw new \Exception("File not found: $file");
        }

        $signals = $this->readSignals();

        ob_start();
        require $file;
        unset($file);

        return ob_get_clean();
    }
}