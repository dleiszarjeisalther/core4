<?php
require_once __DIR__.'/../services/bootstrap.php';

/**
 * Internal API gateway. The web UI talks only to this gateway; each module's
 * database access lives inside its own service. This makes the services easy
 * to split into separate hosts later without changing the UI pages.
 */
function service(string $name): object {
    $all=service_container(); if(!isset($all[$name])) throw new RuntimeException('Unknown service: '.$name); return $all[$name];
}
