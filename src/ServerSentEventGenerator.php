<?php

namespace BoxyBird\Pulsar;

use starfederation\datastar\enums\FragmentMergeMode;
use starfederation\datastar\events\MergeFragments;
use starfederation\datastar\ServerSentEventGenerator as Generator;

class ServerSentEventGenerator extends Generator
{
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

        ob_start();
        require $file;

        return ob_get_clean();
    }
}