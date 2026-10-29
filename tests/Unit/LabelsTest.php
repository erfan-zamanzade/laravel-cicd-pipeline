<?php

namespace Tests\Unit;

use App\Support\Metrics\Labels;
use PHPUnit\Framework\TestCase;

class LabelsTest extends TestCase
{
    public function test_encoding_is_independent_of_label_order(): void
    {
        $this->assertSame(
            Labels::encode(['b' => '2', 'a' => '1']),
            Labels::encode(['a' => '1', 'b' => '2']),
        );
    }

    public function test_round_trip(): void
    {
        $labels = ['method' => 'GET', 'route' => 'health'];

        $this->assertSame($labels, Labels::decode(Labels::encode($labels)));
    }

    public function test_render_escapes_prometheus_special_characters(): void
    {
        $this->assertSame('{path="a\\"b\\\\c"}', Labels::render(['path' => 'a"b\\c']));
        $this->assertSame('', Labels::render([]));
    }
}
