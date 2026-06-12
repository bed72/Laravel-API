<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Testes de Feature usam o TestCase do Laravel + banco limpo por teste.
uses(TestCase::class, RefreshDatabase::class)->in('Feature');

// Testes de Stress rodam contra o servidor real (sem RefreshDatabase).
uses(TestCase::class)->in('Stress');

// Testes de Unit ficam "puros" (sem boot do Laravel) de propósito — rápidos.
