<?php
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ModeOperatoireService
{
    private string $basePath;

    public function __construct(ParameterBagInterface $params)
    {
        $this->basePath = rtrim((string)($params->get('mode_operatoire_path') ?? ''), DIRECTORY_SEPARATOR);
        if ($this->basePath === '') {
            // Fallback vers un dossier local par défaut si non configuré
            $this->basePath = rtrim(getcwd(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'mode_operatoire';
        }
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function listTree(string $relativePath = ''): array
    {
        $root = $this->resolvePath($relativePath);
        if (!is_dir($root)) {
            return [];
        }

        $items = scandir($root) ?: [];
        $result = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $fullPath = $root . DIRECTORY_SEPARATOR . $item;
            $rel = ltrim(($relativePath ? $relativePath . DIRECTORY_SEPARATOR : '') . $item, DIRECTORY_SEPARATOR);
            if (is_dir($fullPath)) {
                $result[] = [
                    'type' => 'dir',
                    'name' => $item,
                    'path' => $rel,
                ];
            } elseif (is_file($fullPath)) {
                $result[] = [
                    'type' => 'file',
                    'name' => $item,
                    'path' => $rel,
                    'size' => filesize($fullPath) ?: 0,
                ];
            }
        }

        // Dossiers d'abord puis fichiers, tri alphabétique
        usort($result, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return $result;
    }

    public function search(string $query, int $maxResults = 200): array
    {
        $query = trim($query);
        if ($query === '') { return []; }

        $results = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->basePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!($fileInfo instanceof \SplFileInfo)) { continue; }
            if ($fileInfo->isDir()) { continue; }

            $relative = ltrim(str_replace($this->basePath, '', $fileInfo->getPathname()), DIRECTORY_SEPARATOR);
            // Match sur le nom de fichier
            if (stripos($fileInfo->getFilename(), $query) !== false) {
                $results[] = [
                    'path' => $relative,
                    'name' => $fileInfo->getFilename(),
                    'match' => 'filename',
                ];
            } else {
                // Match basique du contenu (limite la taille lue)
                $ext = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
                // Ne tenter que sur des fichiers texte simples
                if (in_array($ext, ['txt','md','csv','tsv','json','xml','ini','conf','log','sql','yaml','yml'])) {
                    $content = @file_get_contents($fileInfo->getPathname(), false, null, 0, 200000); // 200KB max
                    if ($content !== false && stripos($content, $query) !== false) {
                        $results[] = [
                            'path' => $relative,
                            'name' => $fileInfo->getFilename(),
                            'match' => 'content',
                        ];
                    }
                }
            }

            if (count($results) >= $maxResults) { break; }
        }

        return $results;
    }

    public function resolvePath(string $relativePath): string
    {
        $relativePath = str_replace(['..', '\\'], ['','/'], $relativePath);
        $full = $this->basePath . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
        $realBase = realpath($this->basePath) ?: $this->basePath;
        $realFull = realpath($full) ?: $full;
        // Sécurité: rester dans la base
        if (strpos($realFull, $realBase) !== 0) {
            return $realBase;
        }
        return $realFull;
    }
}


