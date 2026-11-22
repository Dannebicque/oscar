<?php

namespace App\Instance;

use Psr\Cache\CacheItemPoolInterface;

/** Battement du worker : le planificateur le renouvelle chaque minute ; l'écran Santé s'en sert pour dire si les tâches de fond tournent. */
final class Heartbeat
{
    private const KEY = 'worker_heartbeat';

    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    public function beat(): void
    {
        $this->cache->save($this->cache->getItem(self::KEY)->set(time())->expiresAfter(86400));
    }

    /** Secondes depuis le dernier battement, ou null s'il n'y en a jamais eu. */
    public function age(): ?int
    {
        $i = $this->cache->getItem(self::KEY);

        return $i->isHit() ? max(0, time() - (int) $i->get()) : null;
    }
}
