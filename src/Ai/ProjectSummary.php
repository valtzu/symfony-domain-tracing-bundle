<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Ai;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\RouterInterface;

/**
 * Summarizes what the files of the project tell about the business: its README, its routes and the names of its
 * classes.
 */
final readonly class ProjectSummary
{
    private const int MAX_README_BYTES = 10_000;
    private const int MAX_ROUTES = 200;
    private const int MAX_DIRECTORIES = 100;

    public function __construct(
        private RouterInterface $router,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function describe(): string
    {
        return implode("\n\n", array_filter([
            $this->section('README', $this->readme()),
            $this->section('Routes', $this->routes()),
            $this->section('Classes by directory', $this->classes()),
        ]));
    }

    /**
     * @param list<string> $lines
     */
    private function section(string $title, array $lines): string
    {
        return [] === $lines ? '' : $title.":\n".implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function readme(): array
    {
        $files = iterator_to_array((new Finder())->files()->in($this->projectDir)->depth(0)->name('/^readme/i')->sortByName(), preserve_keys: false);
        $file = reset($files);
        $readme = false === $file ? false : file_get_contents($file->getPathname(), length: self::MAX_README_BYTES);

        return false === $readme ? [] : [$readme];
    }

    /**
     * @return list<string>
     */
    private function routes(): array
    {
        $lines = [];
        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            if (str_starts_with($name, '_')) {
                continue;
            }
            $lines[] = implode(' ', [$name, ...$route->getMethods(), $route->getPath()]);
        }

        return \array_slice($lines, offset: 0, length: self::MAX_ROUTES);
    }

    /**
     * @return list<string>
     */
    private function classes(): array
    {
        if (!is_dir($this->projectDir.'/src')) {
            return [];
        }

        $classesByDirectory = [];
        $finder = (new Finder())->files()->in($this->projectDir.'/src')->name('*.php')->sortByName();
        foreach ($finder as $file) {
            $classesByDirectory[rtrim('src/'.$file->getRelativePath(), characters: '/')][] = $file->getFilenameWithoutExtension();
        }

        $lines = [];
        foreach ($classesByDirectory as $directory => $classes) {
            $lines[] = $directory.': '.implode(', ', $classes);
        }

        return \array_slice($lines, offset: 0, length: self::MAX_DIRECTORIES);
    }
}
