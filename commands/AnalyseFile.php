<?php

declare(strict_types=1);

use Illuminate\Console\Command;

/**
 * Class AnalyseFile
 */
class AnalyseFile extends Command
{
    /**
     * @var string
     */
    protected $signature = 'analise:file';

    /**
     * @var string
     */
    protected $description = 'gregre';

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $definedFunction;

    /**
     * AnalyseFile constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->definedFunction = collect(get_defined_functions()['internal'])
            ->flip()
            ->map(function () {
                return 0;
            });
    }

    /**
     *
     */
    public function handle()
    {
        $start = microtime(true);

        $packages = new LoadPackageInfo();

        $packages->handle()->each(function ($item, $key){
            exec("composer create-project --prefer-dist --no-progress --no-install {$item['name']} stubs/{$key}  2>&1", $output);
            $this->info('install: ' . $key);
        });

        foreach ($this->getDirContents('./stubs') as $file) {
            $content = file_get_contents($file);

            $this->definedFunction = $this->definedFunction->map(function ($count, $key) use ($content) {
                return $count + substr_count($content, $key . '(');
            });
        }

        $this->definedFunction = $this->definedFunction->filter(function ($count) {
            return $count > 0;
        });

        $file = 'build/package.json';
        file_put_contents($file, $this->definedFunction->toJson(), FILE_APPEND | LOCK_EX);

        $time = microtime(true) - $start;
        $this->info('success '.$time);
    }

    /**
     * @param $path
     *
     * @return array
     */
    protected function getDirContents($path)
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        $files = [];
        foreach ($rii as $file) {
            if (!$file->isDir()) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

}