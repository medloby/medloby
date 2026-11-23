<?php

namespace App\Services;

use Closure;
use FilesystemIterator;

final class PublicFileExposure
{
    /**
     * @param  list<string>  $roots
     * @param  list<string>  $protectedPaths
     * @param  (Closure(string): bool)|null  $hasSensitiveName
     */
    public function exposes(array $roots, array $protectedPaths, ?Closure $hasSensitiveName = null): bool
    {
        $protectedPrefixes = array_map(
            fn (string $path): string => $this->prefix($this->resolvePath($path) ?? $path),
            $protectedPaths,
        );
        $visited = [];
        while ($roots !== []) {
            $entry = array_pop($roots);
            $existing = realpath($entry);
            $path = $existing !== false ? $existing : $this->resolvePath($entry);
            if ($path === null || isset($visited[$path])) {
                continue;
            }

            $pathPrefix = $this->prefix($path);
            foreach ($protectedPrefixes as $protectedPrefix) {
                if (str_starts_with($pathPrefix, $protectedPrefix) || str_starts_with($protectedPrefix, $pathPrefix)) {
                    return true;
                }
            }
            if ($hasSensitiveName && $hasSensitiveName(basename($path))) {
                return true;
            }
            if ($existing === false || ! is_dir($path)) {
                continue;
            }
            $visited[$path] = true;

            foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $child) {
                if ($hasSensitiveName && $hasSensitiveName($child->getFilename())) {
                    return true;
                }
                if ($child->isLink() || $child->isDir()) {
                    $roots[] = $child->getPathname();
                }
            }
        }

        return false;
    }

    /**
     * Resolve missing paths as well, since a new backup can make a dangling link reachable.
     *
     * @param  array<string, true>  $visitedLinks
     */
    private function resolvePath(string $path, array $visitedLinks = []): ?string
    {
        $existing = realpath($path);
        if ($existing !== false) {
            return $existing;
        }

        if (is_link($path)) {
            $parent = realpath(dirname($path));
            $target = readlink($path);
            if ($parent === false || $target === false) {
                return null;
            }

            $link = $this->prefix($parent).basename($path);
            if (isset($visitedLinks[$link])) {
                return null;
            }
            $visitedLinks[$link] = true;
            $absolute = str_starts_with($target, '/')
                || (DIRECTORY_SEPARATOR === '\\' && preg_match('~\A(?:[a-z]:)?[/\\\\]~i', $target) === 1);

            return $this->resolvePath($absolute ? $target : $parent.DIRECTORY_SEPARATOR.$target, $visitedLinks);
        }

        $parent = dirname($path);
        $directory = $parent !== $path ? $this->resolvePath($parent, $visitedLinks) : null;
        if ($directory === null) {
            return null;
        }

        return match (basename($path)) {
            '.' => $directory,
            '..' => dirname($directory),
            default => $this->prefix($directory).basename($path),
        };
    }

    private function prefix(string $path): string
    {
        return rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    }
}
