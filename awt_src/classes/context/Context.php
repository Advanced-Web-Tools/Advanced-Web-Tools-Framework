<?php

namespace context;

final class Context
{
    public function __construct(
        public string $contextPath,
        public string $contextName,
        public string $contextId,
    ) {
        $this->contextPath = $contextPath;
        $this->contextName = $contextName;
        $this->contextId = $contextId;
    }
}