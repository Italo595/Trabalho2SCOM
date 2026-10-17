<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{AppException, Logger, Validator};
use App\Repositories\UsuarioRepositoryInterface;

/**
 * Regras de negocio de cadastro e login (sem HTTP, facil de testar).
 */
final class AuthService
{
    public function __construct(private UsuarioRepositoryInterface $usuarios)
    {
    }

    public function register(array $input): array
    {
        (new Validator($input))
            ->text('nome', 2, 80, 'Nome')
            ->email('email')
            ->password('senha')
            ->validate();

        $nome  = Validator::cleanText($input['nome']);
        $email = strtolower(trim($input['email']));

        if ($this->usuarios->findByEmail($email) !== null) {
            throw new AppException('E-mail já cadastrado', 409);
        }

        $hash = password_hash($input['senha'], PASSWORD_BCRYPT, ['cost' => 12]);
        // Cadastro publico SEMPRE cria perfil 'usuario' (nunca admin)
        $id = $this->usuarios->create($nome, $email, $hash, 'usuario');

        Logger::info('Usuário criado', ['id' => $id, 'email' => $email]);
        return ['id' => $id, 'nome' => $nome, 'email' => $email, 'perfil' => 'usuario'];
    }

    public function login(array $input): array
    {
        (new Validator($input))->email('email')->validate();
        if (!isset($input['senha']) || !is_string($input['senha']) || $input['senha'] === '') {
            throw new AppException('Dados inválidos', 422, ['senha' => 'Senha é obrigatória']);
        }

        $email = strtolower(trim($input['email']));
        $user  = $this->usuarios->findByEmail($email);

        if ($user === null) {
            // gasta o mesmo tempo de um login real (evita descobrir e-mails pelo tempo de resposta)
            password_hash($input['senha'], PASSWORD_BCRYPT, ['cost' => 12]);
            $ok = false;
        } else {
            $ok = password_verify($input['senha'], $user['senha_hash']);
        }

        if (!$ok) {
            Logger::warning('Falha de autenticação', ['email' => $email]);
            throw new AppException('E-mail ou senha incorretos', 401);
        }

        Logger::info('Login bem-sucedido', ['id' => $user['id'], 'email' => $email]);
        return [
            'id'     => (int) $user['id'],
            'nome'   => $user['nome'],
            'email'  => $user['email'],
            'perfil' => $user['perfil'],
        ];
    }
}