<?php

namespace Tests\Unit;

use Tests\TestCase;

class MailConfigurationTest extends TestCase
{
    public function test_resend_key_uses_current_render_secret_name(): void
    {
        $originalApiKey = $_SERVER['RESEND_API_KEY'] ?? null;
        $originalKey = $_SERVER['RESEND_KEY'] ?? null;

        try {
            unset($_SERVER['RESEND_API_KEY']);
            $_SERVER['RESEND_KEY'] = 're_test_key';

            $services = require base_path('config/services.php');

            $this->assertSame('re_test_key', $services['resend']['key']);
        } finally {
            if ($originalApiKey === null) {
                unset($_SERVER['RESEND_API_KEY']);
            } else {
                $_SERVER['RESEND_API_KEY'] = $originalApiKey;
            }

            if ($originalKey === null) {
                unset($_SERVER['RESEND_KEY']);
            } else {
                $_SERVER['RESEND_KEY'] = $originalKey;
            }
        }
    }
}
