<?php

declare(strict_types=1);

namespace Snaply\Controllers;

use Snaply\Core\Jwt;
use Snaply\Core\Request;
use Snaply\Core\Response;
use Snaply\Core\Validator;
use Snaply\Models\User;

/**
 * Registrazione, login, logout e utente corrente.
 */
final class AuthController
{
    public function __construct(private User $users = new User())
    {
    }

    /** POST /api/register — crea l'organizzatore e fa auto-login. */
    public function register(Request $request): void
    {
        $body = $request->json();
        if ($body === null) {
            Response::error('BAD_REQUEST', 'Corpo JSON non valido', 400);
        }

        $name = is_string($body['name'] ?? null) ? trim($body['name']) : null;
        $email = is_string($body['email'] ?? null) ? trim($body['email']) : null;
        $password = is_string($body['password'] ?? null) ? $body['password'] : null;

        $v = new Validator();
        $v->required('name', $name, 'Il nome')->length('name', $name, 2, 100, 'Il nome');
        $v->required('email', $email, "L'email")->email('email', $email);
        $v->required('password', $password, 'La password')->minPassword('password', $password);

        if ($v->fails()) {
            Response::error('VALIDATION_ERROR', 'Dati non validi', 422, $v->errors());
        }

        // Email già in uso: conflitto dedicato (409).
        if ($this->users->emailExists($email)) {
            Response::error('EMAIL_TAKEN', 'Email già registrata', 409, ['email' => 'Email già registrata']);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $id = $this->users->create($name, $email, $hash);

        $user = ['id' => $id, 'name' => $name, 'email' => $email];
        $token = Jwt::encode($id, $name);

        Response::success(['user' => $user, 'token' => $token], 201);
    }

    /** POST /api/login — verifica credenziali e ritorna token. */
    public function login(Request $request): void
    {
        $body = $request->json();
        if ($body === null) {
            Response::error('BAD_REQUEST', 'Corpo JSON non valido', 400);
        }

        $email = is_string($body['email'] ?? null) ? trim($body['email']) : '';
        $password = is_string($body['password'] ?? null) ? $body['password'] : '';

        $row = $email !== '' ? $this->users->findByEmail($email) : null;

        // Stesso messaggio sia per email inesistente sia per password errata.
        if ($row === null || !password_verify($password, $row['password_hash'])) {
            Response::error('INVALID_CREDENTIALS', 'Email o password errati', 401);
        }

        $token = Jwt::encode((int) $row['id'], $row['name']);
        Response::success(['user' => User::publicData($row), 'token' => $token], 200);
    }

    /**
     * POST /api/logout — il JWT è stateless: il logout effettivo avviene lato
     * client cancellando il token. L'endpoint esiste per completezza del
     * contratto REST e per un'eventuale futura blacklist dei token.
     */
    public function logout(Request $request): void
    {
        Response::success(['message' => 'Logout effettuato'], 200);
    }

    /** GET /api/me — utente corrente, per ripristinare la sessione al refresh. */
    public function me(Request $request, int $userId): void
    {
        $row = $this->users->findById($userId);
        if ($row === null) {
            Response::error('UNAUTHORIZED', 'Token non valido o scaduto', 401);
        }
        Response::success(['user' => User::publicData($row)], 200);
    }
}
