<?php

namespace App\Console\Commands\Fias;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;


/**
 * Импорт базы ФИАС
 */

class Import extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fias:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import FIAS tables';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $params = [
            'house' => [
                'insertInto' => 'fias_house',
                'rows' => 'House'
            ],
            'addrob' => [
                'insertInto' => 'fias_addrob',
                'rows' => 'Object'
            ]
        ];

        $dir = database_path('fias_xml');
        $table = $this->anticipate('Какую таблицу импортировать? (общее для файлов название)', array_keys($params));
        $defaultParams = Arr::get($params, $table, []);

        /**
         * @var SplFileInfo[]|Collection $files
         */
        $files = collect(File::files($dir))
            ->filter(function (SplFileInfo $file) use ($table) {
                return preg_match("/.+?{$table}.+?/iu", $file->getBasename());
            });


        if (count($files)) {
            $filesChoose = $files->map(function (SplFileInfo $file) {
                return $file->getBaseName();
            });

            $this->info('Будут импортированы следующие файлы');
            foreach ($filesChoose as $key => $file) {
                $this->info(sprintf('[%s] %s', $key, $file));
            }

            if ($this->confirm('Нужно ли исключить некоторые файлы?', true)) {
                $excludeFiles = $this->choice('Исключите ненужные файлы(через запятую)?', $filesChoose->toArray(), null, null, true);
                $files = $files->filter(function (SplFileInfo $file) use ($excludeFiles) {
                    return !in_array($file->getBasename(), $excludeFiles);
                });
            }
        } else {
            $files = collect(File::files($dir));

            $filesChoose = $files->map(function (SplFileInfo $file) {
                return $file->getBaseName();
            });

            $excludeFiles = $this->choice('Какие файлы импортировать?', $filesChoose->toArray(), null, null, true);
            $files = $files->filter(function (SplFileInfo $file) use ($excludeFiles) {
                return in_array($file->getBasename(), $excludeFiles);
            });
        }


        $table_name = $this->ask('Название таблицы:', Arr::get($defaultParams, 'insertInto', null));
        $rows_identified = $this->ask('Название элемента сущности:', Arr::get($defaultParams, 'rows', null));


        $this->info(PHP_EOL);
        $bar = $this->output->createProgressBar(count($files));
        $bar->setFormat('%current%/%max% [%bar%] %percent:3s%% processing file : %message%');

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $bar->setMessage($file->getFilename());
            $bar->advance();

            $query = "LOAD XML LOCAL INFILE '{$filePath}' INTO TABLE `{$table_name}` ROWS IDENTIFIED BY '<{$rows_identified}>';";

            DB::unprepared($query);
        }


        $bar->finish();

        $this->info(PHP_EOL);


    }
}
