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
     * MenuManager constructor.
     *
     * @param array $roles Roles to scan menu items for.
     */
    public function __construct(array $roles)
    {
        $this->roles = $roles;
    }

    /**
     * Scan menu items for configured roles and populate the menuItems property.
     *
     * @return void
     */
    public function scan(): void
    {
        $this->menuItems = MenuScanner::scanMenuItems($this->roles);
    }

    /**
     * Get the menu items scanned for each role.
     *
     * @return array Scanned menu items keyed by role.
     */
    public function getMenuItems(): array
    {
        return $this->menuItems;
    }
}
