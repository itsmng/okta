<?php
/**
 * PHPUnit bootstrap file for Okta plugin tests.
 *
 * This file sets up the testing environment without requiring the full ITSM-NG installation.
 */

// Register autoloader for plugin classes
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Stub class for GLPI's Auth class.
 * Provides the auth type constants used by UserImportService.
 */
if (!class_exists('Auth')) {
    class Auth
    {
        public const EXTERNAL = 4;
        public const LDAP = 3;
    }
}
