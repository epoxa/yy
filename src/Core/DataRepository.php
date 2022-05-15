<?php

namespace YY\Core;

interface DataRepository
{
    function initializeStorage(bool $writable): void;
    function isObjectExists(string $yyid): bool;
    function writeSerializedObject(string $yyid, string $objectData): void;
    function readSerializedObject(string $yyid): ?string;
    function deleteObject(string $yyid): void;
    function tryProcessUncommitedChanges(): void;
    function getStatistics(): array;
    function detachStorage(): void;
}