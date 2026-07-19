<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

// Force populate getenv() with environment variables from $_ENV and $_SERVER for SatuSehat
foreach ([$_ENV, $_SERVER] as $envSource) {
    foreach ($envSource as $key => $val) {
        if (strpos($key, 'SATUSEHAT_') === 0 && !empty($val) && is_string($val)) {
            putenv("$key=$val");
        }
    }
}
