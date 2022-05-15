<?php
namespace YY\System\Log;

interface LogInterface
{
    public function Reset();
    
    public function SetProfile($name);

    public function Log($kind, $message);

    public function GetScreenOutput();

    public function GetStatistics();

    public static function Disable();

    public static function Enable();
}
