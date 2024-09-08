<?php

namespace App\Trait;

use App\DTO\GameDetail;
use Symfony\Component\Console\Input\InputInterface;

trait TargetDirectoryTrait
{
    private function getTargetDir(InputInterface $input, GameDetail $game, ?string $subdirectory = null): string
    {
        $dir = $input->getArgument('directory');
        if (PHP_OS_FAMILY === 'Windows') {
            if (!preg_match('/^[a-zA-Z]:\\\\/', $dir)) {
                $dir = getcwd() . '/' . $dir;
            }
        } else {
            if (!str_starts_with($dir, '/') && !preg_match('@^[0-9a-zA-Z.]+://.+$@', $dir)) {
                $dir = getcwd() . '/' . $dir;
            }
        }

        $title = preg_replace('@[^a-zA-Z-_0-9.]@', '_', $game->title);
        $title = preg_replace('@_{2,}@', '_', $title);

        $dir = "{$dir}/{$title}";
        if ($subdirectory !== null) {
            $dir .= "/{$subdirectory}";
        }

        return $dir;
    }
}
