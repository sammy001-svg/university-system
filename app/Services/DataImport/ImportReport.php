<?php
declare(strict_types=1);

namespace App\Services\DataImport;

/** What an import did: one line per record, with notes, plus totals. */
final class ImportReport
{
    /** @var list<string> */
    private array $lines = [];
    public int $created = 0;
    public int $updated = 0;
    public int $failed  = 0;
    /** @var array<string,string> label => "x of y" summary rows */
    private array $summary = [];

    public function line(string $text): void
    {
        $this->lines[] = $text;
    }

    public function note(string $text): void
    {
        $this->lines[] = '        note: ' . $text;
    }

    public function summary(string $label, string $value): void
    {
        $this->summary[$label] = $value;
    }

    public function totals(): string
    {
        return "{$this->created} created, {$this->updated} updated, {$this->failed} failed";
    }

    public function text(): string
    {
        $out = $this->lines;
        if ($this->summary !== []) {
            $out[] = str_repeat('-', 72);
            foreach ($this->summary as $label => $value) {
                $out[] = sprintf('%-44s %s', $label, $value);
            }
        }
        $out[] = $this->totals() . '.';
        return implode("\n", $out);
    }
}
