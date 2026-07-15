<?php
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ModeOperatoireService
{
    private string $basePath;

    public function __construct(ParameterBagInterface $params)
    {
        $configured = rtrim((string) ($params->get('mode_operatoire_path') ?? ''), "\\/");
        $this->basePath = $this->normalizePath($configured);
        if ($this->basePath === '') {
            $this->basePath = $this->normalizePath(
                rtrim((string) getcwd(), "\\/") . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'mode_operatoire'
            );
        }
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @return array{configuredPath: string, resolvedPath: string, isDir: bool, isReadable: bool, itemCount: int|null, phpUser: string, lastError: string|null}
     */
    public function getDiagnostics(): array
    {
        $resolved = realpath($this->basePath) ?: $this->basePath;
        $items = @scandir($resolved);
        $lastError = error_get_last();

        return [
            'configuredPath' => $this->basePath,
            'resolvedPath' => $resolved,
            'isDir' => is_dir($resolved),
            'isReadable' => is_readable($resolved),
            'itemCount' => $items === false ? null : max(0, count($items) - 2),
            'phpUser' => (string) get_current_user(),
            'lastError' => ($items === false && is_array($lastError)) ? ($lastError['message'] ?? null) : null,
        ];
    }

    public function listTree(string $relativePath = ''): array
    {
        $root = $this->resolvePath($relativePath);
        if (!is_dir($root)) {
            return [];
        }

        $items = @scandir($root);
        if ($items === false) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $fullPath = $root . DIRECTORY_SEPARATOR . $item;
            $rel = ltrim(($relativePath ? $relativePath . DIRECTORY_SEPARATOR : '') . $item, "\\/");
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
        if ($query === '' || !is_dir($this->basePath)) {
            return [];
        }

        $results = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $this->basePath,
                    \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO
                ),
                \RecursiveIteratorIterator::SELF_FIRST,
                \RecursiveIteratorIterator::CATCH_GET_CHILD
            );

            foreach ($iterator as $fileInfo) {
                if (!($fileInfo instanceof \SplFileInfo)) {
                    continue;
                }
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $relative = ltrim(str_replace($this->basePath, '', $fileInfo->getPathname()), "\\/");
                if (stripos($fileInfo->getFilename(), $query) !== false) {
                    $results[] = [
                        'path' => $relative,
                        'name' => $fileInfo->getFilename(),
                        'match' => 'filename',
                    ];
                } else {
                    $ext = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
                    if (in_array($ext, ['txt', 'md', 'csv', 'tsv', 'json', 'xml', 'ini', 'conf', 'log', 'sql', 'yaml', 'yml'], true)) {
                        $content = @file_get_contents($fileInfo->getPathname(), false, null, 0, 200000);
                        if ($content !== false && stripos($content, $query) !== false) {
                            $results[] = [
                                'path' => $relative,
                                'name' => $fileInfo->getFilename(),
                                'match' => 'content',
                            ];
                        }
                    }
                }

                if (count($results) >= $maxResults) {
                    break;
                }
            }
        } catch (\Throwable) {
            return $results;
        }

        return $results;
    }

    public function resolvePath(string $relativePath): string
    {
        $relativePath = str_replace(['..', '\\'], ['', '/'], $relativePath);
        $relativePath = trim($relativePath, '/');
        $full = $relativePath === ''
            ? $this->basePath
            : $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!$this->isPathInsideBase($full)) {
            return $this->basePath;
        }

        return realpath($full) ?: $full;
    }

    private function isPathInsideBase(string $candidatePath): bool
    {
        $base = strtolower($this->normalizePath(realpath($this->basePath) ?: $this->basePath));
        $candidate = strtolower($this->normalizePath(realpath($candidatePath) ?: $candidatePath));

        return $candidate === $base || str_starts_with($candidate, $base . DIRECTORY_SEPARATOR);
    }

    private function normalizePath(string $path): string
    {
        $path = trim(str_replace('/', DIRECTORY_SEPARATOR, $path));
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, '\\\\')) {
            return rtrim($path, "\\/");
        }

        return rtrim($path, "\\/");
    }
}
