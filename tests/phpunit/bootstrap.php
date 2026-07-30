<?php
/**
 * PHPUnit bootstrap file
 */

require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/vendor/autoload.php';

// Initialize Brain Monkey
\Brain\Monkey\setUp();

// Define ABSPATH to prevent exit in plugin files
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( dirname( dirname( __FILE__ ) ) ) . '/' );
}

// Include the files we want to test
require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/helpers.php';
