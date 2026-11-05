<?php

namespace Modules\Core\Entities;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Compatibility alias for legacy imports.
 *
 * New code should import Spatie\Permission\Models\Role directly.
 */
class Role extends SpatieRole
{
}
