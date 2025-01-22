<?php

/**
 * Plugin Name: Pulsar
 * Plugin URI: https://github.com/boxybird/pulsar
 * Description: Pulsar integrates Server-Sent Events (SSE) into WordPress using Datastar.js, enabling real-time data streaming.
 * Version: 0.0.1
 * Author: Andrew Rhyand
 * Author URI: https://andrewrhyand.com
 * License: MIT
 * Text Domain: pulsar
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!file_exists(__DIR__.'/vendor/autoload.php')) {
    exit('Please run "composer install" in the plugin directory before activating the plugin.');
}

require_once __DIR__.'/vendor/autoload.php';

BoxyBird\Pulsar\Pulsar::init();

