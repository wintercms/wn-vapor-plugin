<?php

namespace Winter\Vapor\Console;

use Aws\Command;
use Aws\Credentials\CredentialProvider;
use Aws\Credentials\Credentials;
use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;
use GuzzleHttp\Promise;
use File;
use Storage;
use System\Console\WinterMirror;
use Symfony\Component\Console\Input\InputOption;

/**
 * Console command to implement a "public" folder.
 *
 * This command will create symbolic links to files and directories
 * that are commonly required to be publicly available.
 *
 * @package winter\wn-system-module
 * @author Alexey Bobkov, Samuel Georges
 */
class VaporMirror extends WinterMirror
{
    /**
     * The console command name.
     */
    protected $name = 'vapor:mirror';

    /**
     * The console command description.
     */
    protected $description = 'Generates a mirrored public folder using symbolic links.';

    /**
     * Extended to add custom feature support
     * {@inheritDocs}
     */
    public function handle()
    {
        $target = $this->getDestinationPath();

        if ($this->option('rm') && is_dir($target)) {
            File::deleteDirectory($target);
        }

        parent::handle();

        if ($this->option('disk')) {
            $this->uploadDirectoryToS3($target, $this->argument('destination'), $this->option('disk'));
        }

        if ($this->option('rm')) {
            File::deleteDirectory($target);
        }
    }

    /**
     * Uploads the total contents of a dir to s3 recursively
     *
     * @param string $target
     * @param string $dest
     * @param string $disk
     * @return void
     */
    protected function uploadDirectoryToS3(string $target, string $dest, string $disk)
    {
        $s3Client = $this->getS3Client($disk);

        $bucket = config('filesystems.disks.s3.bucket');
        $promises = [];
        $i = 0;

        foreach ($this->findFiles($target) as $keyToFile => $pathToFile) {
            $promises[] = (new MultipartUploader($s3Client, $pathToFile, [
                'bucket'          => $bucket,
                'key'             => $dest . '/' . $keyToFile,
                'concurrency'     => $this->option('concurrency'),
                'before_complete' => function (Command $command) use ($keyToFile) {
                    $this->info('Complete: ' . $keyToFile);
                },
                'before_upload'   => function (Command $command) use ($keyToFile) {
                    $this->warn('Uploading: ' . $keyToFile);
                }
            ]))->promise();

            // at the concurrency limit, resolve available promises
            if (count($promises) >= $this->option('concurrency')) {
                $this->resolvePromises($promises);
            }

            if (++$i >= 800) {
                // at 800 iterations, we're close to the limit of uploads we can make per session, so we remake the
                // client to reset the session and continue, it's less than ideal
                $this->resolvePromises($promises);
                $s3Client = $this->getS3Client($disk);
                $i = 0;
            }
        }

        // if we have any remaining promises to resolve, now is that time
        if (count($promises)) {
            $this->resolvePromises($promises);
        }
    }

    /**
     * Recursively iterates through a path and yields each file found
     *
     * @param string $target
     * @return iterable
     */
    protected function findFiles(string $target): iterable
    {
        foreach (
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $target,
                    \RecursiveDirectoryIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::SELF_FIRST
            ) as $item
        ) {
            if ($item->isDir() || $item->getSize() === 0) {
                continue;
            }

            yield $iterator->getSubPathname() => $item->getRealPath();
        }
    }

    /**
     * Create a S3Client based on a disk config
     *
     * @param string $disk
     * @return S3Client
     */
    protected function getS3Client(string $disk): S3Client
    {
        $config =  config(sprintf('filesystems.disks.%s', $disk));
        $config['version'] = 'latest';
        $config['credentials'] = [
            'key' => config(sprintf('filesystems.disks.%s.key', $disk)),
            'secret' => config(sprintf('filesystems.disks.%s.secret', $disk))
        ];

        return new S3Client($config);
    }

    /**
     * Resolves all promises passed and then resets promises array
     *
     * @param array $promises
     * @return void
     */
    protected function resolvePromises(array &$promises)
    {
        $aggregate = Promise\Utils::all($promises);

        try {
            $result = $aggregate->wait();
        } catch (\Throwable $e) {
            // Handle the error
            dump($e->getMessage());
            exit(1);
        }

        $promises = [];
    }

    /**
     * Extended to remove echo
     * {@inheritDocs}
     */
    protected function mirrorFile($file): bool
    {
        $src = base_path() . '/' . $file;

        $dest = $this->getDestinationPath() . '/' . $file;

        if (!File::isFile($src) || File::isFile($dest)) {
            return false;
        }

        return $this->mirror($src, $dest);
    }

    /**
     * Extended to remove echo
     * {@inheritDocs}
     */
    protected function mirrorDirectory($directory): bool
    {
        $src = base_path() . '/' . $directory;

        $dest = $this->getDestinationPath() . '/' . $directory;

        if (!File::isDirectory($src) || File::isDirectory($dest)) {
            return false;
        }

        if (!File::isDirectory(dirname($dest))) {
            File::makeDirectory(dirname($dest), 0755, true);
        }

        return $this->mirror($src, $dest);
    }

    /**
     * Extended to provide copying functionality
     * {@inheritDocs}
     */
    protected function mirror($src, $dest): bool
    {
        if ($this->option('relative')) {
            $src = $this->getRelativePath($dest, $src);

            if (strpos($src, '../') === 0) {
                $src = rtrim(substr($src, 3), '/');
            }
        }

        foreach ($this->option('ignore') as $ignore) {
            if (preg_match($ignore, $src)) {
                $this->warn('ignoring: ' . $src);
                return false;
            }
        }

        if (is_dir($src) && !is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        if (is_file($src)) {
            !is_dir(dirname($dest)) && mkdir(dirname($dest), 0755, true);
            copy($src, $dest);
            return true;
        }

        foreach (
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $src,
                    \RecursiveDirectoryIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::SELF_FIRST
            ) as $item
        ) {
            if ($item->isDir()) {
                mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
                continue;
            }
            copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
        }

        $this->info('Mirrored: ' . $dest);

        return true;
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['disk', null, InputOption::VALUE_REQUIRED, 'Copy the mirror to a disk.'],
            ['concurrency', null, InputOption::VALUE_REQUIRED, 'Files to upload at a time.', 25],
            ['relative', null, InputOption::VALUE_NONE, 'Create symlinks relative to the public directory.'],
            ['rm', null, InputOption::VALUE_NONE, 'Remove target before mirror'],
            ['ignore', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Specify patterns to ignore'],
        ];
    }
}
