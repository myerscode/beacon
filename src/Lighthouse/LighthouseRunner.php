<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Lighthouse;

use RuntimeException;
use Symfony\Component\Process\Process;

class LighthouseRunner
{
    /**
     * @var string[]
     */
    private array $chromeFlags = ['--headless', '--no-sandbox'];

    private string $chromePath = '';

    private string $formFactor = 'mobile';
    private string $lighthouseBinary = 'lighthouse';

    private int $timeout = 120;

    /**
     * @param string[] $flags
     */
    public function chromeFlags(array $flags): self
    {
        $this->chromeFlags = $flags;

        return $this;
    }

    public function chromePath(string $path): self
    {
        $this->chromePath = $path;

        return $this;
    }

    public function desktop(): self
    {
        $this->formFactor = 'desktop';

        return $this;
    }

    public function formFactor(string $formFactor): self
    {
        $this->formFactor = $formFactor;

        return $this;
    }

    public function lighthouseBinary(string $path): self
    {
        $this->lighthouseBinary = $path;

        return $this;
    }

    public function mobile(): self
    {
        $this->formFactor = 'mobile';

        return $this;
    }

    public function run(string $url): LighthouseResult
    {
        $command = [
            $this->lighthouseBinary,
            $url,
            '--output=json',
            '--quiet',
            '--form-factor=' . $this->formFactor,
        ];

        if ($this->formFactor === 'desktop') {
            $command[] = '--preset=desktop';
        }

        if ($this->chromeFlags !== []) {
            $command[] = '--chrome-flags=' . implode(' ', $this->chromeFlags);
        }

        $env = [];

        if ($this->chromePath !== '') {
            $env['CHROME_PATH'] = $this->chromePath;
        }

        $process = new Process($command, null, $env !== [] ? $env : null);
        $process->setTimeout($this->timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                'Lighthouse failed: ' . $process->getErrorOutput(),
            );
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        return new LighthouseResult($data);
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }
}
