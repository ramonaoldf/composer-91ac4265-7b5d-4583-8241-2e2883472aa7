<?php

namespace Laravel\VaporCli\Commands;

use Laravel\VaporCli\Dockerfile;
use Laravel\VaporCli\GitIgnore;
use Laravel\VaporCli\Helpers;
use Laravel\VaporCli\Manifest;
use Laravel\VaporCli\Path;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class EnvCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('env')
            ->addArgument('environment', InputArgument::REQUIRED, 'The environment name')
            ->addOption('docker', null, InputOption::VALUE_NONE, 'Indicate that the environment will use Docker images as its runtime')
            ->addOption('no-vanity-domain', null, InputOption::VALUE_NONE, 'Indicate that the environment should not be assigned vanity domains')
            ->setDescription('Create a new environment');
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        Helpers::ensure_api_token_is_available();

        if ($this->argument('environment') === 'testing') {
            Helpers::abort('This environment name is reserved by Vapor. Please choose another environment name.');
        }

        $this->vapor->createEnvironment(
            Manifest::id(),
            $environment = $this->argument('environment'),
            $this->option('docker'),
            ! $this->option('no-vanity-domain')
        );

        Manifest::addEnvironment($environment, [
            'memory' => 1024,
            'cli-memory' => 512,
            'runtime' => $this->option('docker') ? 'docker' : 'php-8.4:al2',
            'build' => [
                'COMPOSER_MIRROR_PATH_REPOS=1 composer install --no-dev',
                'php artisan event:cache',
                file_exists(Path::current().'/webpack.mix.js')
                    ? 'npm ci && npm run prod && rm -rf node_modules'
                    : 'npm ci && npm run build && rm -rf node_modules',
            ],
        ]);

        if ($this->option('docker')) {
            Dockerfile::fresh($environment);
        }

        GitIgnore::add(['.env.'.$environment]);

        Helpers::info('Environment created successfully.');
    }
}
