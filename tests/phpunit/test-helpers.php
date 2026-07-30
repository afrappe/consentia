<?php

use PHPUnit\Framework\TestCase;
use Brain\Monkey\Functions;

class Test_Helpers extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        // Mock translation function used in consentia_default_settings()
        Functions\stubs([
            '__' => function($text, $domain) { return $text; }
        ]);

        // Mock wp_parse_args
        Functions\stubs([
            'wp_parse_args' => function ($args, $defaults) {
                return array_merge($defaults, $args);
            }
        ]);
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_consentia_get_existing_key() {
        Functions\expect('get_option')
            ->once()
            ->with('consentia_settings', [])
            ->andReturn([
                'title' => 'Custom Title',
                'consent_type' => 'optin',
            ]);

        $this->assertEquals('Custom Title', consentia_get('title'));
    }

    public function test_consentia_get_missing_key_with_default() {
        Functions\expect('get_option')
            ->once()
            ->with('consentia_settings', [])
            ->andReturn([
                'title' => 'Custom Title',
            ]);

        $this->assertEquals('fallback', consentia_get('non_existent_key', 'fallback'));
    }

    public function test_consentia_get_missing_key_no_default() {
        Functions\expect('get_option')
            ->once()
            ->with('consentia_settings', [])
            ->andReturn([
                'title' => 'Custom Title',
            ]);

        $this->assertEquals('', consentia_get('non_existent_key'));
    }

    public function test_consentia_get_default_settings_fallback() {
        Functions\expect('get_option')
            ->once()
            ->with('consentia_settings', [])
            ->andReturn([]); // Empty option

        // Should return the default setting from consentia_default_settings()
        $this->assertEquals('Usamos cookies', consentia_get('title'));
    }
}
