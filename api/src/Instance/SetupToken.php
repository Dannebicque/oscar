<?php

namespace App\Instance;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Code d'installation : à la première utilisation, n'importe qui pourrait devenir administrateur d'une instance exposée sur Internet.
 * Le code est donc généré au premier démarrage, affiché dans les journaux du serveur (ou imposé par SETUP_TOKEN), et exigé par l'assistant.
 */
final class SetupToken
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%env(default::SETUP_TOKEN)%')] private readonly ?string $fromEnv,
        #[Autowire('%env(APP_SHARE_DIR)%')] private readonly string $shareDir,
    ) {
    }

    private function path(): string
    {
        $dir = str_starts_with($this->shareDir, '/') ? $this->shareDir : $this->projectDir.'/'.$this->shareDir;

        return rtrim($dir, '/').'/setup-token';
    }

    /** Le code attendu, ou null s'il n'a pas encore été créé. */
    public function current(): ?string
    {
        if ($this->fromEnv !== null && $this->fromEnv !== '') {
            return $this->fromEnv;
        }
        $f = $this->path();

        return is_file($f) ? trim((string) file_get_contents($f)) ?: null : null;
    }

    /** Crée le code s'il n'existe pas encore (idempotent) et le renvoie. */
    public function ensure(): string
    {
        if (($c = $this->current()) !== null) {
            return $c;
        }
        $dir = \dirname($this->path());
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $code = bin2hex(random_bytes(8));
        file_put_contents($this->path(), $code."\n");
        chmod($this->path(), 0600);

        return $code;
    }

    public function check(string $given): bool
    {
        $expected = $this->current();

        return $expected !== null && hash_equals($expected, trim($given));
    }

    /** L'installation est faite : le code ne sert plus. */
    public function discard(): void
    {
        if (is_file($f = $this->path())) {
            @unlink($f);
        }
    }
}
