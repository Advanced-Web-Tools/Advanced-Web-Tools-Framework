<?php

namespace router\interface;

interface IRequestProvider
{
    public function current(): IRequest;
}
