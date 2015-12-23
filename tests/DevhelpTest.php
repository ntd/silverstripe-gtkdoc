<?php

class DevhelpTest extends PHPUnit_Framework_TestCase
{

    public function testProcess()
    {
        $devhelp = new Devhelp(1234);
        $this->assertFalse($devhelp->process());

        $devhelp = new Devhelp('');
        $this->assertFalse($devhelp->process());

        $devhelp = new Devhelp(null);
        $this->assertFalse($devhelp->process());

        $devhelp = new Devhelp(array('1234'));
        $this->assertFalse($devhelp->process());

        $devhelp = new Devhelp('1234');
        $this->assertTrue($devhelp->process());
    }

    public function testTOC()
    {
        $devhelp = new Devhelp(file_get_contents(__DIR__ . '/adg.devhelp2'));
        $this->assertEquals($devhelp->getTOC(), array());
        $this->assertEmpty($devhelp->getTOC(), array());
        $this->assertTrue($devhelp->process());
        $this->assertNotEmpty($devhelp->getTOC());

        $toc = $devhelp->getTOC();
        $this->assertEquals($toc['Introduction.html']['parent']['link'], '');
        $this->assertEquals($toc['']['name'], '');
        $this->assertArrayNotHasKey('parent', $toc['']);
        $this->assertArrayHasKey('sub', $toc['']);
        $this->assertEquals($toc['NEWS.html']['name'], 'News archive');
        $this->assertEquals($toc['NEWS.html']['parent']['link'], 'Introduction.html');
        $this->assertEquals($toc['Matrix.html']['parent']['link'], 'Core-gboxed.html');
        $this->assertArrayNotHasKey('sub', $toc['NEWS.html']);
        $this->assertArrayHasKey('sub', $toc['Introduction.html']);
        $this->assertArrayNotHasKey('sub', $toc['Matrix.html']);
        $this->assertArrayHasKey('sub', $toc['Core.html']);
    }
}
