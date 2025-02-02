# Pulsar

Pulsar integrates Server-Sent Events (SSE) into WordPress using [Datastar](https://data-star.dev/), enabling real-time data streaming.

## Installation

```
cd wp-content/plugins
git clone https://github.com/boxybird/pulsar
cd pulsar
composer install
```
> Location: /wp-config.php

```php
define('PULSAR_ENCRYPTION_KEY', 'SOME_RANDOM_16_CHARACTER_STRING');
```


> Location: /wp-admin/plugins.php

Activate plugin

> Location: /wp-admin/options-permalink.php

Visit and refresh permalinks by clicking **"Save Changes"** button

## Important

By default, this package pre-bundles the Datastar library. If you site already has Datastar installed, you should dequeue this
packages version to avoid conflicts.

> Location: /your-theme/functions.php

```php
add_action('wp_enqueue_scripts', function () {
    wp_dequeue_script('pulsar-datastar-script');
});
```

## Usage

```HTML
<div id="pulsar-mount">
    <button data-on-click="@get('/pulsar/v1/my-hook')">GET REQUEST</button>
    <div id="random"></div>
</div>    
```

```PHP
add_action('pulsar/get/my-hook', function (starfederation\datastar\ServerSentEventGenerator $sse, array $params) {
    $sse->mergeFragments('<div id="random">'.random_int(1, 10000).'</div>');
}, 10, 2);
```

---

```HTML
<div id="pulsar-mount">
    <button data-on-click="@get('/pulsar/v1/my-other-hook')">GET REQUEST - @FOOBAR</button>
    <div id="random"></div>
</div>
```

```PHP
add_action('pulsar/get/my-other-hook', function (starfederation\datastar\ServerSentEventGenerator $sse, array $params) {
    $sse->mergeFragments('<div id="random">'.random_int(1, 10000).'</div>');
}, 10, 2);
```

---

```HTML
<div id="pulsar-mount">
    <button data-on-click="@post('/pulsar/v1/my-hook')">POST REQUEST</button>
    <div id="random"></div>
</div>
```

```PHP
add_action('pulsar/post/my-hook', function (starfederation\datastar\ServerSentEventGenerator $sse, array $params) {
    for ($i = 0; $i < 10; $i++) {
        $sse->mergeFragments('<div id="random">'.random_int(1, 10000).'</div>');

        usleep(10000);
    }
}, 10, 2);
```

---

```HTML
<div id="pulsar-mount">
    <button data-on-click="@put('/pulsar/v1/my-hook')">PUT</button>
    <button data-on-click="@patch('/pulsar/v1/my-hook')">PATCH</button>
    <button data-on-click="@delete('/pulsar/v1/my-hook')">DELETE</button>
</div>
```

```PHP
add_action('pulsar/put/my-hook', ...);
add_action('pulsar/patch/my-hook', ...);
add_action('pulsar/delete/my-hook', ...);
```

## Reference

[https://github.com/starfederation/datastar-php](https://github.com/starfederation/datastar-php)