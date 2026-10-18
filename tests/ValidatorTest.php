<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    use AppAssertions;

    public function testEmailValidoPassa(): void
    {
        (new Validator(['email' => 'ana@exemplo.com']))->email('email')->validate();
        $this->addToAssertionCount(1); // nenhuma excecao lancada
    }

    public function testEmailInvalidoGera422(): void
    {
        foreach (['sem-arroba', 'a@', "' OR '1'='1", ''] as $email) {
            $this->assertAppError(422, fn() => (new Validator(['email' => $email]))->email('email')->validate());
        }
    }

    public function testSenhaFracaRejeitadaESenhaForteAceita(): void
    {
        foreach (['curta1', 'somenteletras', '12345678'] as $senha) {
            $this->assertAppError(422, fn() => (new Validator(['senha' => $senha]))->password('senha')->validate());
        }
        $this->assertAppError(422, fn() => (new Validator(['senha' => ['array']]))->password('senha')->validate());

        (new Validator(['senha' => 'senha1234']))->password('senha')->validate();
        $this->addToAssertionCount(1);
    }

    public function testSanitizacaoRemoveTagsHtml(): void
    {
        $this->assertSame('alert(1)Teste', Validator::cleanText('<script>alert(1)</script>Teste'));
        $this->assertSame('Mewtwo', Validator::cleanText('  <b>Mew</b>two  '));
    }

    public function testIdDaUrlRejeitaValoresInvalidos(): void
    {
        foreach (['0', 'abc', '-1', '1; DROP TABLE usuarios', ''] as $id) {
            $this->assertAppError(400, fn() => Validator::id($id));
        }
        $this->assertSame(5, Validator::id('5'));
    }

    public function testUrlComEsquemaPerigosoERejeitada(): void
    {
        foreach (['javascript:alert(1)', 'ftp://exemplo.com/a.png', 'nao-e-url'] as $url) {
            $this->assertAppError(422, fn() => (new Validator(['imagem_url' => $url]))->optionalUrl('imagem_url')->validate());
        }
        (new Validator(['imagem_url' => 'https://exemplo.com/a.png']))->optionalUrl('imagem_url')->validate();
        $this->addToAssertionCount(1);
    }
}