<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CoreBundle\Form\Data\Condition\ConditionInterface;

final class TableItemAction
{
    /** @var bool */
    private $post;
    /** @var string */
    private $route;
    /** @var array<string, mixed> */
    private $routeParameters;
    /** @var string */
    private $icon;
    /** @var string */
    private $labelKey;
    /** @var string|null */
    private $messageKey;
    private bool $dynamic;
    private string $buttonType = 'default';
    /** @var ConditionInterface[] */
    private array $conditions = [];

    /**
     * @param array<string, mixed> $routeParameters
     */
    private function __construct(bool $post, string $route, string $labelKey, string $icon, ?string $messageKey, array $routeParameters, bool $dynamic = false)
    {
        $this->post = $post;
        $this->route = $route;
        $this->routeParameters = $routeParameters;
        $this->labelKey = $labelKey;
        $this->icon = $icon;
        $this->messageKey = $messageKey;
        $this->dynamic = $dynamic;
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public static function postAction(string $route, string $labelKey, string $icon, string $messageKey, array $routeParameters = []): TableItemAction
    {
        return new self(true, $route, $labelKey, $icon, $messageKey, $routeParameters);
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public static function getAction(string $route, string $labelKey, string $icon, array $routeParameters = []): TableItemAction
    {
        return new self(false, $route, $labelKey, $icon, null, $routeParameters);
    }

    /**
     * @param array<string, string> $routeParameters
     */
    public static function postDynamicAction(string $route, string $labelKey, string $icon, string $messageKey, array $routeParameters = []): TableItemAction
    {
        return new self(true, $route, $labelKey, $icon, $messageKey, $routeParameters, true);
    }

    /**
     * @param array<string, string> $routeParameters
     */
    public static function getDynamicAction(string $route, string $labelKey, string $icon, array $routeParameters = []): TableItemAction
    {
        return new self(false, $route, $labelKey, $icon, null, $routeParameters, true);
    }

    public function isPost(): bool
    {
        return $this->post;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    public function getLabelKey(): string
    {
        return $this->labelKey;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getMessageKey(): ?string
    {
        return $this->messageKey;
    }

    public function isDynamic(): bool
    {
        return $this->dynamic;
    }

    public function setDynamic(bool $dynamic): void
    {
        $this->dynamic = $dynamic;
    }

    public function setButtonType(string $buttonType): void
    {
        $this->buttonType = $buttonType;
    }

    public function getButtonType(): string
    {
        return $this->buttonType;
    }

    public function addCondition(ConditionInterface $condition): TableItemAction
    {
        $this->conditions[] = $condition;

        return $this;
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    public function valid($objectOrArray): bool
    {
        foreach ($this->conditions as $condition) {
            if (!$condition->valid($objectOrArray)) {
                return false;
            }
        }

        return true;
    }
}
