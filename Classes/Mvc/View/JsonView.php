<?php

declare(strict_types=1);

namespace Netlogix\Nxajax\Mvc\View;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3Fluid\Fluid\View\ViewInterface;

class JsonView implements ViewInterface
{
    protected $exposeSettings = false;

    protected $variables = [];

    public function setExposeSettings(bool $exposeSettings)
    {
        $this->exposeSettings = $exposeSettings;
    }

    public function render()
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
        } else {
            return json_encode($variables, JSON_THROW_ON_ERROR);
        }
    }

    public function assign($key, $value)
    {
        $this->variables[$key] = $value;

        return $this;
    }

    public function assignMultiple(array $values)
    {
        foreach ($values as $key => $value) {
            $this->assign($key, $value);
        }

        return $this;
    }

    public function renderSection($sectionName, array $variables = [], $ignoreUnknown = false)
    {
    }

    public function renderPartial($partialName, $sectionName, array $variables, $ignoreUnknown = false)
    {
    }
}
