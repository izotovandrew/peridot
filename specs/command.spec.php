<?php
use Evenement\EventEmitter;
use Peridot\Configuration;
use Peridot\Console\Application;
use Peridot\Console\Command;
use Peridot\Console\Environment;
use Peridot\Console\InputDefinition;
use Peridot\Core\Suite;
use Peridot\Core\Test;
use Peridot\Reporter\ReporterFactory;
use Peridot\Runner\Runner;
use Peridot\Runner\SuiteLoader;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

describe('Command', function() {
    beforeEach(function() {
        $this->configuration = new Configuration();
        $this->configuration->setGrep('*.stub.php');

        $this->emitter = new EventEmitter();
        $suite = new Suite("suite", function() {});
        $this->runner = new Runner($suite, $this->configuration, $this->emitter);
        $this->output = new BufferedOutput();
        $this->factory = new ReporterFactory($this->configuration, $this->output, $this->emitter);
        $this->definition = new InputDefinition();

        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'notaspec.php';
        $environment = new Environment($this->definition, $this->emitter, ['c' => $path]);
        $this->application = new Application($environment);

        $this->command = new Command($this->runner, $this->configuration, $this->factory, $this->emitter);
        $this->command->setApplication($this->application);
    });

    describe('loader accessors', function() {
        it('should allow setting and getting of loader', function() {
            $loader = new SuiteLoader('*.stub.php');
            $this->command->setLoader($loader);
            assert($loader === $this->command->getLoader(), 'loader should be accessible from command');
        });

        it('should allow getting a default loader', function() {
            assert(!is_null($this->command->getLoader()), "command should have default loader");
        });
    });

    describe('->run()', function() {
        it('should emit an execute event', function() {
            $input = null;
            $output = null;
            $this->emitter->on('peridot.execute', function($i, $o) use (&$input, &$output) {
                $input = $i;
                $output = $o;
            });

            $this->command->run(new ArrayInput([], $this->definition), $this->output);

            assert(!is_null($input), "input should have been received by event");
            assert(!is_null($output), "output should have been received by event");
        });

        it('should emit a reporters event', function() {
            $input = null;
            $factory = null;
            $this->emitter->on('peridot.reporters', function($i, $f) use (&$input, &$factory) {
                $input = $i;
                $factory = $f;
            });

            $this->command->run(new ArrayInput([], $this->definition), $this->output);

            assert(!is_null($input), "input should have been received by event");
            assert($factory === $this->factory, "reporter factory should have been received by event");
        });

        context('when using the --reporters option', function() {
            it('should list reporters', function() {
                $this->command->run(new ArrayInput(['--reporters' => true], $this->definition), $this->output);
                $reporters = $this->factory->getReporters();
                $content = $this->output->fetch();
                foreach ($reporters as $name => $info) {
                    assert(strstr($content, "$name - " . $info['description']) !== false,  "reporter $name should be displayed");
                }
            });
        });

        context('when passing a reporter name', function() {
            it('should set the reporter name on the configuration object', function() {
                $this->factory->register('test', 'desc', function() {});
                $this->command->run(new ArrayInput(['-r' => 'test'], $this->definition), $this->output);
                $reporter = $this->configuration->getReporter();
                assert($reporter == 'test', 'reporter name should be "test"');
            });
        });

        it('should emit a load event', function() {
            $command = null;
            $config = null;
            $this->emitter->on('peridot.load', function($cmd, $cfg) use (&$command, &$config) {
                $command = $cmd;
                $config = $cfg;
            });

            $this->command->run(new ArrayInput([], $this->definition), $this->output);

            assert($command === $this->command, "command should have been received by event");
            assert($config === $this->configuration, "configuration should have been received by event");
        });

        context('when there are failing tests', function() {
            it('should return an exit code', function() {
                $suite = new Suite("fail suite", function() {});
                $test = new Test('fail', function() { throw new Exception('fail'); });
                $suite->addTest($test);
                $runner = new Runner($suite, $this->configuration, $this->emitter);
                $command = new Command($runner, $this->configuration, $this->factory, $this->emitter);
                $command->setApplication($this->application);
                $exit = $command->run(new ArrayInput([], $this->definition), $this->output);

                assert($exit == 1, "exit code should be 1");
            });
        });
    });
});
