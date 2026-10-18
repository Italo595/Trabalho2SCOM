<?php
declare(strict_types=1);

namespace Tests;

use App\Core\AppException;

/**
 * Ajuda os testes a conferir o status HTTP de um erro da aplicacao.
 */
trait AppAssertions
{
    private function assertAppError(int $status, callable $acao): void
    {
        try {
            $acao();
        } catch (AppException $e) {
            $this->assertSame($status, $e->status());
            return;
        }
        $this->fail("Esperava AppException com status $status, mas nada foi lançado");
    }
}