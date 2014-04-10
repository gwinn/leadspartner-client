<?php

namespace Varifort\Console;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    protected $parameters = null;

    public function setParameters($params)
    {
        if (!is_array($params)) {
            throw new \InvalidArgumentException('Parameters must be array type');
        }
        $this->parameters = $params;
    }
    public function getParameters()
    {
        return $this->parameters;
    }
}