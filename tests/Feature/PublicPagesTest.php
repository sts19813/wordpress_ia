<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_privacy_policy_is_publicly_available(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('Política de privacidad')
            ->assertSee('Pulsorsur')
            ->assertSee('Facebook, Instagram y otros servicios externos');
    }
}
