<?php

namespace Antropomorf\Admin;

use Antropomorf\Utilities\MenuScanner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MenuManager
 *
 * Scans and provides menu items for configured user roles.
 *
 * @package Antropomorf\Admin
 */
class MenuManager
{
    private array $menuItems = [];
    private array $roles;

    /**
     * @param array $roles Roles to scan menu items for.
     */
    public function __construct(array $roles)
    {
        $this->roles = $roles;
    }

    public function scan(): void
    {
        $this->menuItems = MenuScanner::scanMenuItems($this->roles);
    }

    /**
     * @return array Menu items keyed by role.
     */
    public function getMenuItems(): array
    {
        return $this->menuItems;
    }
}
