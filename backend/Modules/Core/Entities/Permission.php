<?php

namespace Modules\Core\Entities;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Compatibility alias for legacy imports.
 *
 * New code should import Spatie\Permission\Models\Permission directly.
 */
class Permission extends SpatiePermission
{
}
