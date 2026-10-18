<?php
declare(strict_types=1);

namespace Tests;

use App\Repositories\UsuarioRepositoryInterface;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    use AppAssertions;

    public function testCadastroGravaHashBcryptEIgnoraPerfilEnviado(): void
    {
        $repo = $this->createMock(UsuarioRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn(null);
        $repo->expects($this->once())->method('create')
            ->with(
                'Ana',
                'ana@exemplo.com',
                $this->callback(fn($hash) => $hash !== 'senha1234' && password_verify('senha1234', $hash)),
                'usuario' // mesmo que o cliente envie perfil=admin, grava 'usuario'
            )
            ->willReturn(10);

        $r = (new AuthService($repo))->register([
            'nome' => 'Ana', 'email' => 'Ana@Exemplo.com', 'senha' => 'senha1234', 'perfil' => 'admin',
        ]);

        $this->assertSame(10, $r['id']);
        $this->assertSame('usuario', $r['perfil']);
        $this->assertSame('ana@exemplo.com', $r['email']);
    }

    public function testCadastroComEmailDuplicadoGera409(): void
    {
        $repo = $this->createMock(UsuarioRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn(['id' => 1]);
        $repo->expects($this->never())->method('create');

        $this->assertAppError(409, fn() => (new AuthService($repo))->register([
            'nome' => 'Ana', 'email' => 'ana@exemplo.com', 'senha' => 'senha1234',
        ]));
    }

    public function testCadastroComDadosInvalidosGera422(): void
    {
        $repo = $this->createMock(UsuarioRepositoryInterface::class);
        $repo->expects($this->never())->method('create');

        $this->assertAppError(422, fn() => (new AuthService($repo))->register([
            'nome' => 'A', 'email' => 'invalido', 'senha' => '1',
        ]));
    }

    public function testLoginCorretoRetornaUsuarioSemHash(): void
    {
        $repo = $this->createStub(UsuarioRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn([
            'id' => '3', 'nome' => 'Ana', 'email' => 'ana@exemplo.com', 'perfil' => 'usuario',
            'senha_hash' => password_hash('senha1234', PASSWORD_BCRYPT, ['cost' => 4]),
        ]);

        $r = (new AuthService($repo))->login(['email' => 'ana@exemplo.com', 'senha' => 'senha1234']);

        $this->assertSame(3, $r['id']);
        $this->assertArrayNotHasKey('senha_hash', $r);
    }

    public function testLoginComSenhaErradaGera401(): void
    {
        $repo = $this->createStub(UsuarioRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn([
            'id' => '3', 'nome' => 'Ana', 'email' => 'ana@exemplo.com', 'perfil' => 'usuario',
            'senha_hash' => password_hash('senha1234', PASSWORD_BCRYPT, ['cost' => 4]),
        ]);

        $this->assertAppError(401, fn() => (new AuthService($repo))->login([
            'email' => 'ana@exemplo.com', 'senha' => 'outra-senha1',
        ]));
    }

    public function testLoginComEmailInexistenteGera401(): void
    {
        $repo = $this->createStub(UsuarioRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn(null);

        $this->assertAppError(401, fn() => (new AuthService($repo))->login([
            'email' => 'ninguem@exemplo.com', 'senha' => 'senha1234',
        ]));
    }

    public function testLoginComEmailMalformadoGera422SemConsultarBanco(): void
    {
        $repo = $this->createMock(UsuarioRepositoryInterface::class);
        $repo->expects($this->never())->method('findByEmail');

        $this->assertAppError(422, fn() => (new AuthService($repo))->login([
            'email' => "' OR '1'='1", 'senha' => 'qualquer1',
        ]));
    }
}