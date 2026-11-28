<?php

declare(strict_types=1);

/**
 * Dependency-free test runner (works on any host with PHP CLI, no PHPUnit needed).
 */
final class TestRunner
{
    private int $passed = 0;
    /** @var list<string> */
    private array $failures = [];
    private string $current = '';
    private int $assertions = 0;

    public function test(string $name, callable $fn): void
    {
        $this->current = $name;
        $before = count($this->failures);
        try {
            $fn($this);
        } catch (\Throwable $e) {
            $this->failures[] = "[{$name}] Exception " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
        }
        if (count($this->failures) === $before) {
            $this->passed++;
            fwrite(STDOUT, "  \033[32m✔\033[0m {$name}\n");
        } else {
            fwrite(STDOUT, "  \033[31m✘\033[0m {$name}\n");
        }
    }

    public function section(string $title): void
    {
        fwrite(STDOUT, "\n\033[1m{$title}\033[0m\n");
    }

    public function assert(bool $condition, string $message = 'assertion failed'): void
    {
        $this->assertions++;
        if (!$condition) {
            $this->failures[] = "[{$this->current}] {$message}";
        }
    }

    public function equals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assert($expected === $actual, ($message !== '' ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }

    public function contains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assert(str_contains($haystack, $needle), ($message !== '' ? $message . ': ' : '') . "'{$needle}' not found");
    }

    public function throws(callable $fn, string $class = \Throwable::class, string $message = ''): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->assert($e instanceof $class, ($message ?: 'wrong exception') . ': got ' . get_class($e) . ' ' . $e->getMessage());
            return;
        }
        $this->assert(false, ($message ?: 'expected exception') . ' ' . $class);
    }

    public function finish(): int
    {
        $total = $this->passed + count(array_unique(array_map(fn ($f) => strtok($f, ']'), $this->failures)));
        fwrite(STDOUT, "\n");
        foreach ($this->failures as $f) {
            fwrite(STDOUT, "\033[31mFAIL\033[0m {$f}\n");
        }
        fwrite(STDOUT, sprintf("\nTests: %d passed, %d failed, %d assertions\n", $this->passed, $total - $this->passed, $this->assertions));
        return $this->failures === [] ? 0 : 1;
    }
}
