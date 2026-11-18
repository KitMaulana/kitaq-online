<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** UA default: Chrome Android (browser yang diizinkan gerbang browser). */
    public const UA_CHROME_ANDROID = 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['User-Agent' => self::UA_CHROME_ANDROID]);
        $this->withoutVite();
        $this->withCredentials(); // request JSON (API ujian) ikut membawa cookie sesi
    }

    /**
     * Cookie jar sederhana: cookie dari respons dikirim lagi di request berikutnya (seperti browser),
     * agar ID sesi stabil — penting untuk aturan satu sesi aktif & alur token ujian.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->isCleared()) {
                unset($this->unencryptedCookies[$cookie->getName()]);
            } else {
                $this->unencryptedCookies[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $response;
    }

    /** Staf selalu login di guard web (auth:student mengubah guard default di instance aplikasi test). */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        if ($guard === null && $user instanceof \App\Models\User) {
            $guard = 'web';
        }

        return parent::actingAs($user, $guard);
    }

    /** Lupakan cookie (simulasi perangkat/browser lain). */
    protected function newDevice(): static
    {
        $this->unencryptedCookies = [];
        $this->flushSession();
        app('auth')->forgetGuards();

        return $this;
    }
}
