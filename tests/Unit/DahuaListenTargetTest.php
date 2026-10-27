<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DahuaListenTargetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2).'/scripts/dahua-listen.php';
    }

    public function test_local_upstream_stays_on_http(): void
    {
        $target = upstream_target('192.168.1.67:8000', '/ezapi/push/init');

        $this->assertSame('http://192.168.1.67:8000/ezapi/push/init', $target['url']);
        $this->assertSame('192.168.1.67:8000', $target['host']);
        $this->assertFalse($target['https']);
    }

    public function test_production_upstream_uses_https_and_the_domain_host(): void
    {
        $target = upstream_target('https://hrms.autotechmotors.com', '/ezapi/push/event?RegisterID=BF04139PAJ2DC86');

        $this->assertSame(
            'https://hrms.autotechmotors.com/ezapi/push/event?RegisterID=BF04139PAJ2DC86',
            $target['url'],
        );
        $this->assertSame('hrms.autotechmotors.com', $target['host']);
        $this->assertTrue($target['https']);
    }
}
