<?php
namespace runtime\interface;
interface IRuntimeFlagHandler
{
    public function normalize(array $flags): array;
}
