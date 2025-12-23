<?php

/**
 * RBAC - Roles Canvas
 *
 * Files included:
 *  - app/Modules/Admin/RoleController.php
 *  - app/Services/RoleService.php
 *  - app/Domain/Repository/RoleRepository.php
 *
 * Endpoints (wire into routes.php):
 *  - GET    /v1/admin/roles                         (admin:roles:list)
 *  - POST   /v1/admin/roles                         (admin:roles:create)
 *  - PATCH  /v1/admin/roles/{id}                    (admin:roles:update)
 *  - DELETE /v1/admin/roles/{id}                    (admin:roles:delete)
 *  - POST   /v1/admin/roles/{id}/permissions        (admin:roles:assign_permissions)
 */

namespace App\Modules\Admin;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Micro;

final class RoleController
{
    public function __construct(private DiInterface $di) {}

    public function list(Micro $app)
    {
        $responder = $this->di->getShared('apiResponder');
        try {
            return $responder->ok($this->di->getShared('roleService')->list(), 200);
        } catch (\Throwable) {
            return $responder->error('INTERNAL_ERROR', 'Failed to list roles', 500);
        }
    }

    public function get(Micro $app, int $id)
    {
        $responder = $this->di->getShared('apiResponder');
        try {
            return $responder->ok($this->di->getShared('roleService')->get($id), 200);
        } catch (\Throwable) {
            return $responder->error('INTERNAL_ERROR', 'Failed to get role', 500);
        }
    }

    public function read(Micro $app, int $id)
    {
        $responder = $this->di->getShared('apiResponder');
        try {
            $role = $this->di->getShared('roleService')->get($id);
            return $responder->ok($role, 200);
        } catch (\RuntimeException) {
            return $responder->error('NOT_FOUND', 'Role not found', 404);
        } catch (\Throwable) {
            return $responder->error('INTERNAL_ERROR', 'Failed to read role', 500);
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
            $role = $this->di->getShared('roleService')->create($payload);
            return $responder->ok($role, 201);
        } catch (\InvalidArgumentException $e) {
            return $responder->error('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\Throwable) {
            return $responder->error('INTERNAL_ERROR', 'Failed to create role', 500);
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
            $role = $this->di->getShared('roleService')->update($id, $payload);
            return $responder->ok($role, 200);
        } catch (\InvalidArgumentException $e) {
            return $responder->error('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\RuntimeException) {
            return $responder->error('NOT_FOUND', 'Role not found', 404);
        } catch (\Throwable) {
            return $responder->error('INTERNAL_ERROR', 'Failed to update role', 500);
        }
    }

    public function delete(Micro $app, int $id)
    {
        $responder = $this->di->getShared('apiResponder');
        try {
            $this->di->getShared('roleService')->delete($id);
            return $responder->ok((object)[], 200);
        } catch (\InvalidArgumentException $e) {
            return $responder->error('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\RuntimeException) {
            return $responder->error('NOT_FOUND', 'Role not found', 404);
        } catch (\Throwable) {
            return $responder->error('INTERNAL_ERROR', 'Failed to delete role', 500);
        }
    }

    public function assignPermissions(Micro $app, int $id)
    {
        $responder = $this->di->getShared('apiResponder');
        $payload = $app->request->getJsonRawBody(true);
        if (!is_array($payload)) {
            return $responder->error('INVALID_JSON', 'Invalid JSON payload', 400);
        }

        $perms = $payload['permissions'] ?? null;
        if (!is_array($perms)) {
            return $responder->error('VALIDATION_ERROR', 'permissions must be an array', 400);
        }

        try {
            $role = $this->di->getShared('roleService')->assignPermissions($id, $perms);
            return $responder->ok($role, 200);
        } catch (\InvalidArgumentException $e) {
            return $responder->error('VALIDATION_ERROR', $e->getMessage(), 400);
        } catch (\RuntimeException) {
            return $responder->error('NOT_FOUND', 'Role not found', 404);
        } catch (\Throwable) {
            return $responder->error('INTERNAL_ERROR', 'Failed to assign permissions', 500);
        }
    }
}