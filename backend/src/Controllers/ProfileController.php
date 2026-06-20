<?php

declare(strict_types=1);

namespace Snaply\Controllers;

use Snaply\Core\Request;
use Snaply\Core\Response;
use Snaply\Core\Validator;
use Snaply\Models\User;

/**
 * Aggiornamento del profilo dell'organizzatore (nome, email, password).
 */
final class ProfileController
{
    public function __construct(private User $users = new User())
    {
    }

    /** PUT /api/profile — aggiorna i campi presenti, con regole di sicurezza. */
    public function update(Request $request, int $userId): void
    {
        $current = $this->users->findById($userId);
        if ($current === null) {
            Response::error('UNAUTHORIZED', 'Utente non trovato', 401);
        }

        $body = $request->json();
        if ($body === null) {
            Response::error('BAD_REQUEST', 'Corpo JSON non valido', 400);
        }

        $name = isset($body['name']) && is_string($body['name']) ? trim($body['name']) : null;
        $email = isset($body['email']) && is_string($body['email']) ? trim($body['email']) : null;
        $currentPassword = is_string($body['current_password'] ?? null) ? $body['current_password'] : null;
        $newPassword = is_string($body['new_password'] ?? null) ? $body['new_password'] : null;

        $wantsEmailChange = $email !== null && strcasecmp($email, $current['email']) !== 0;
        $wantsPasswordChange = $newPassword !== null && $newPassword !== '';

        // Validazione dei campi presenti.
        $v = new Validator();
        if ($name !== null) {
            $v->required('name', $name, 'Il nome')->length('name', $name, 2, 100, 'Il nome');
        }
        if ($email !== null) {
            $v->required('email', $email, "L'email")->email('email', $email);
        }
        if ($wantsPasswordChange) {
            $v->minPassword('new_password', $newPassword);
        }
        if ($v->fails()) {
            Response::error('VALIDATION_ERROR', 'Dati non validi', 422, $v->errors());
        }

        // Cambiare email o password richiede la password attuale corretta.
        if ($wantsEmailChange || $wantsPasswordChange) {
            if ($currentPassword === null || !password_verify($currentPassword, $current['password_hash'])) {
                Response::error('INVALID_CREDENTIALS', 'Password attuale errata', 401, [
                    'current_password' => 'Password attuale errata',
                ]);
            }
        }

        // Email nuova deve essere unica.
        if ($wantsEmailChange && $this->users->emailExists($email, $userId)) {
            Response::error('EMAIL_TAKEN', 'Email già registrata', 409, ['email' => 'Email già registrata']);
        }

        // Costruisce l'update solo con ciò che cambia davvero.
        $fields = [];
        if ($name !== null && $name !== $current['name']) {
            $fields['name'] = $name;
        }
        if ($wantsEmailChange) {
            $fields['email'] = $email;
        }
        if ($wantsPasswordChange) {
            $fields['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $this->users->update($userId, $fields);

        $updated = $this->users->findById($userId);
        Response::success(['user' => User::publicData($updated)], 200);
    }
}
