<?php

namespace App\Entity;

/** Donnée d'un utilisateur : jamais visible des autres (§2 : données isolées, mails privés jamais partagés). */
interface OwnedInterface
{
    public function getOwner(): ?User;

    public function setOwner(User $owner): static;
}
