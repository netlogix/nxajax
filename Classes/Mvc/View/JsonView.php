<?php

declare(strict_types=1);

namespace Netlogix\Nxajax\Mvc\View;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3Fluid\Fluid\View\ViewInterface;

class JsonView implements ViewInterface
{
    protected bool $exposeSettings = false;

    protected array $variables = [];

    public function setExposeSettings(bool $exposeSettings): void
    {
        $this->exposeSettings = $exposeSettings;
    }

    public function assign($key, $value): static
    {
        $this->variables[$key] = $value;

        return $this;
    }

    public function assignMultiple(array $values): static
    {
        foreach ($values as $key => $value) {
            $this->assign($key, $value);
        }

        return $this;
    }

    public function render(): string
    {
        $variables = $this->variables;
        if (!$this->exposeSettings) {
            unset($variables['settings']);
        }

        foreach ($variables as $key => $variable) {
            if ($variable instanceof QueryResultInterface) {
                $variables[$key] = $variable->toArray();
            }
        }

        if ((is_countable($variables) ? count($variables) : 0) === 1) {
            return json_encode(current($variables), JSON_THROW_ON_ERROR);
        }

        return json_encode($variables, JSON_THROW_ON_ERROR);
    }

    public function renderSection($sectionName, array $variables = [], $ignoreUnknown = false)
    {
    }

    public function renderPartial($partialName, $sectionName, array $variables, $ignoreUnknown = false)
    {
    }
}
