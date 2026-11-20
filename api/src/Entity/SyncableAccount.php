<?php

namespace App\Entity;

/** Compte synchronisé (messagerie ou agenda) : porte son état de connexion (F-77). */
interface SyncableAccount
{
    public function getOwner(): ?User;
    public function getLabel(): string;
    public function markSynced(): void;
    public function markFailed(string $message): void;
    public function getFailureCount(): int;
}
