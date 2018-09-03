<?php

namespace JoeDixon\Translation\Console\Commands;

use Illuminate\Console\Command;
use JoeDixon\Translation\Scanner;
use Illuminate\Filesystem\Filesystem;
use JoeDixon\Translation\Drivers\File;
use JoeDixon\Translation\Drivers\Database;
use JoeDixon\Translation\Drivers\Translation;

class MergeTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translation:merge-translations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge translations between drivers';

    /**
     * File scanner
     *
     * @var Scanner
     */
    private $scanner;

    /**
     * Translation
     *
     * @var Translation
     */
    private $translation;

    /**
     * Translation drivers
     *
     * @var array
     */
    private $drivers = ['file', 'database'];

    /**
     * Languages
     *
     * @var array
     */
    private $languages;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Scanner $scanner, Translation $translation)
    {
        parent::__construct();
        $this->scanner = $scanner;
        $this->translation = $translation;
        $this->languages = array_keys($this->translation->allLanguages()->toArray());
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $fromDriver = $this->anticipate(__('translation::translation.prompt_from_driver'), $this->drivers);
        if (!in_array($fromDriver, $this->drivers)) {
            return $this->error(__('translation::translation.invalid_driver'));
        }

        $toDriver = $this->anticipate(__('translation::translation.prompt_to_driver'), $this->drivers);
        if (!in_array($toDriver, $this->drivers)) {
            return $this->error(__('translation::translation.invalid_driver'));
        }

        $language = $this->anticipate(__('translation::translation.prompt_language_if_any'), $this->languages);
        if ($language && !in_array($language, $this->languages)) {
            return $this->error(__('translation::translation.invalid_language'));
        }

        $fromDriver = $this->createDriver($fromDriver);
        $toDriver = $this->createDriver($toDriver);

        $this->line(__('translation::translation.merging'));

        if ($language) {
            $this->mergeTranslations($toDriver, $language, $fromDriver->allTranslationsFor($language));
        } else {
            $translations = $this->mergeLanguages($toDriver, $fromDriver->allTranslations());
        }

        $this->info(__('translation::translation.merged'));
    }

    private function createDriver($driver)
    {
        if ($driver === 'file') {
            return new File(new Filesystem, app('path.lang'), config('app.locale'), $this->scanner);
        }

        return new Database(config('app.locale'), $this->scanner);
    }

    private function mergeLanguages($driver, $languages)
    {
        foreach ($languages as $language => $translations) {
            $this->mergeTranslations($driver, $language, $translations);
        }
    }

    private function mergeTranslations($driver, $language, $translations)
    {
        $this->mergeGroupTranlsations($driver, $language, $translations['group']);
        $this->mergeSingleTranlsations($driver, $language, $translations['single']);
    }

    private function mergeGroupTranlsations($driver, $language, $groups)
    {
        foreach ($groups as $group => $translations) {
            foreach ($translations as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $driver->addGroupTranslation($language, "{$group}.{$key}", $value);
            }
        }
    }

    private function mergeSingleTranlsations($driver, $language, $translations)
    {
        foreach ($translations as $key => $value) {
            $driver->addSingleTranslation($language, $key, $value);
        }
    }
}
