<?php
/**
 * RBAC - Permissions Canvas
 *
 * Files included:
 *  - app/Modules/Admin/PermissionController.php
 *  - app/Services/PermissionService.php
 *  - app/Domain/Repository/PermissionRepository.php
 *
 * Endpoints (wire into routes.php):
 *  - GET  /v1/admin/permissions     (admin:permissions:list)
 */

namespace App\Modules\Admin;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Micro;

final class PermissionController
{
    public function __construct(private DiInterface $di) {}

    public function list(Micro $app)
    {
        $responder = $this->di->getShared('apiResponder');
        $q = $app->request->getQuery('q', null, null);

        try {
            return $responder->ok($this->di->getShared('permissionService')->list($q), 200);
        } catch (\Throwable) {
            return $responder->error('INTERNAL_ERROR', 'Failed to list permissions', 500);
        }
    }
}