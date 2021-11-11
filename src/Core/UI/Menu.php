<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

class Menu
{
    /** @var MenuEntry[] */
    private array $children = [];

    public function addChild(string $getLabel, string $getIcon, string $url, ?string $color = null): void
    {
        $this->children[] = new MenuEntry($getLabel, $getIcon, $url, $color);
    }

    /**
     * @return MenuEntry[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }
}