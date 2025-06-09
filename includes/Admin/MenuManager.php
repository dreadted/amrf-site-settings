<?php

namespace Antropomorf\Admin;

use Antropomorf\Utilities\MenuScanner;

if (!defined('ABSPATH')) {
    exit;
}

class MenuManager
{
    private array $menuItems = [];
    private array $roles;

    public function __construct(array $roles)
    {
        $this->roles = $roles;
    }

    public function scan(): void
    {
        $this->menuItems = MenuScanner::scanMenuItems($this->roles);
    }

    public function getMenuItems(): array
    {
        return $this->menuItems;
    }
}
