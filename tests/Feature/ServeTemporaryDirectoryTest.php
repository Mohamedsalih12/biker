<?php

namespace Tests\Feature;

use Illuminate\Foundation\Console\ServeCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ServeTemporaryDirectoryTest extends TestCase
{
    public function test_windows_development_server_keeps_writable_temporary_directories_when_reloading(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows development-server environment regression.');
        }

        $originalEnvironment = $_ENV;
        $_ENV['TEMP'] = sys_get_temp_dir();
        $_ENV['TMP'] = sys_get_temp_dir();

        try {
            $command = new class extends ServeCommand
            {
                public function probe(): Process
                {
                    return $this->startProcess(true);
                }

                protected function serverCommand(): array
                {
                    return [PHP_BINARY, '-r', 'echo json_encode(["temp" => getenv("TEMP"), "tmp" => getenv("TMP"), "writable" => is_resource(tmpfile())]);'];
                }
            };
            $command->setLaravel($this->app);
            $command->setInput(new ArrayInput([], $command->getDefinition()));
            $process = $command->probe();
            $process->wait();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame(sys_get_temp_dir(), $result['temp']);
            $this->assertSame(sys_get_temp_dir(), $result['tmp']);
            $this->assertTrue($result['writable']);
        } finally {
            $_ENV = $originalEnvironment;
        }
    }
}
