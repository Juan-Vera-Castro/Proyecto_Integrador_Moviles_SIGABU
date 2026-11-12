<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * En el servidor Apache termina HTTPS y le habla al contenedor por HTTP: Laravel solo le cree
 * la cabecera X-Forwarded-Proto a ese proxy, para generar los enlaces con https.
 */
class ProxyHttpsTest extends TestCase
{
    public function test_detras_del_proxy_del_servidor_los_enlaces_salen_con_https(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/login')
            ->assertSee('action="https://', false);
    }

    public function test_un_cliente_externo_no_puede_hacerse_pasar_por_el_proxy(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '200.1.2.3'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/login')
            ->assertDontSee('action="https://', false);
    }
}
