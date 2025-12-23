<?php

/**
 * RBAC - User Management Canvas
 *
 * Files included:
 *  - app/Modules/Admin/UserController.php
 *  - app/Services/UserService.php
 *  - app/Domain/Repository/UserRepository.php
 *  - app/Database/Migrations/005_create_user_profiles.php
 *
 * Behavior:
 *  - JSON-only requests
 *  - Standard API envelope (apiResponder)
 *  - RBAC protected via route permission mapping in routes.php
 *
 * Endpoints (wire into routes.php):
 *  - GET    /v1/admin/users                (users:list)
 *  - POST   /v1/admin/users                (users:create)
 *  - GET    /v1/admin/users/{id}           (users:read)
 *  - PATCH  /v1/admin/users/{id}           (users:update)
 *  - POST   /v1/admin/users/{id}/disable   (users:disable)
 *  - POST   /v1/admin/users/{id}/roles     (admin:users:assign_roles)
 */

namespace App\Modules\Admin;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Micro;

final class UserController
{
    public function __construct(private DiInterface $di) {}

    public function list(Micro $app)
    {
        $responder = $this->di->getShared('apiResponder');
        $req = $app->request;

        $page = (int)$req->getQuery('page', 'int', 1);
        $pageSize = (int)$req->getQuery('page_size', 'int', 20);
        $q = $req->getQuery('q', null, null);

        try {
            $data = $this->di->getShared('userService')->list($page, $pageSize, $q);
            return $responder->ok($data, 200);
        } catch (\Throwable $e) {
            return $responder->error('INTERNAL_ERROR', 'Failed to list users', 500);
        }
    }

    public function create(Micro $app)
    {
        $responder = $this->di->getShared('apiResponder');
        $payload = $app->request->getJsonRawBody(true);
        if (!is_array($payload)) {
            return $responder->error('INVALID_JSON', 'Invalid JSON payload', 400);
        }

        try {
            $user = $this->di->getShared('userService')->create($payload);
            return $responder->ok($user, 201);
        } catch (\InvalidArgumentException $e) {
            return $responder->error('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $responder->error('INTERNAL_ERROR', 'Failed to create user', 500);
        }
    }

    public function read(Micro $app, int $id)
    {
        $responder = $this->di->getShared('apiResponder');
        try {
            $user = $this->di->getShared('userService')->get($id);
            return $responder->ok($user, 200);
        } catch (\RuntimeException) {
            return $responder->error('NOT_FOUND', 'User not found', 404);
        } catch (\Throwable) {
            return $responder->error('INTERNAL_ERROR', 'Failed to read user', 500);
        }
    }

    public function update(Micro $app, int $id)
    {
        $responder = $this->di->getShared('apiResponder');
        $payload = $app->request->getJsonRawBody(true);
        if (!is_array($payload)) {
            return $responder->error('INVALID_JSON', 'Invalid JSON payload', 400);
        }

        try {
            $user = $this->di->getShared('userService')->update($id, $payload);
            return $responder->ok($user, 200);
        } catch (\InvalidArgumentException $e) {
            return $responder->error('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\RuntimeException) {
            return $responder->error('NOT_FOUND', 'User not found', 404);
        } catch (\Throwable) {
            return $responder->error('INTERNAL_ERROR', 'Failed to update user', 500);
        }
    }

    public function disable(Micro $app, int $id)
    {
        $responder = $this->di->getShared('apiResponder');
        try {
            $this->di->getShared('userService')->disable($id);
            return $responder->ok((object)[], 200);
        } catch (\RuntimeException) {
            return $responder->error('NOT_FOUND', 'User not found', 404);
        } catch (\Throwable) {
            return $responder->error('INTERNAL_ERROR', 'Failed to disable user', 500);
        }
    }

    public function assignRoles(Micro $app, int $id)
    {
        $responder = $this->di->getShared('apiResponder');
        $payload = $app->request->getJsonRawBody(true);
        if (!is_array($payload)) {
            return $responder->error('INVALID_JSON', 'Invalid JSON payload', 400);
        }

        $roles = $payload['roles'] ?? null;
        if (!is_array($roles)) {
            return $responder->error('VALIDATION_ERROR', 'roles must be an array', 400);
        }

        try {
            $user = $this->di->getShared('userService')->assignRoles($id, $roles);
            return $responder->ok($user, 200);
        } catch (\InvalidArgumentException $e) {
            return $responder->error('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\RuntimeException) {
            return $responder->error('NOT_FOUND', 'User not found', 404);
        } catch (\Throwable) {
            return $responder->error('INTERNAL_ERROR', 'Failed to assign roles', 500);
        }
    }
}