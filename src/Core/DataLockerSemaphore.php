<?php

namespace YY\Core;

interface DataLockerSemaphore
{
    function Lock($data): void;
    function Unlock($data): void;
}