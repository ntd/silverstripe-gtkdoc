<?php

require_once '../code/GtkdocHtml.php';

class GtkdocHtmlTest extends PHPUnit_Framework_TestCase {

    public function testProcess() {
        $html = new GtkdocHtml(1234);
        $this->assertFalse($html->process());

        $html = new GtkdocHtml('');
        $this->assertFalse($html->process());

        $html = new GtkdocHtml(null);
        $this->assertFalse($html->process());

        $html = new GtkdocHtml(array('1234'));
        $this->assertFalse($html->process());

        $html = new GtkdocHtml('1234');
        $this->assertTrue($html->process());
    }

    public function testMangleRules() {
        $html = new GtkdocHtml(@file_get_contents('AdgGtkLayout.html'));
        $default_rules = $html->getMangleRules();
        $this->assertNotEmpty($default_rules);
        $html->resetMangleRules(true);
        $this->assertEmpty($html->getMangleRules());
        $html->addMangleRules(array('a', 'dummy' => 'b'));
        $this->assertEquals(2, count($html->getMangleRules()));
        $html->addMangleRules(array('key' => 'value'));
        $rules = $html->getMangleRules();
        $this->assertEquals(3, count($rules));
        $this->assertEquals(key($rules), 'key');
        $this->assertEquals(current($rules), 'value');
        $html->addMangleRules(array('key' => 'other value'));
        $rules = $html->getMangleRules();
        $this->assertEquals(3, count($rules));
        $this->assertEquals(key($rules), 'key');
        $this->assertEquals(current($rules), 'other value');
        $html->addMangleRules(array('dummy' => 'b again'));
        $rules = $html->getMangleRules();
        $this->assertEquals(3, count($rules));
        $this->assertEquals(key($rules), 'dummy');
        $this->assertEquals(current($rules), 'b again');
        $html->resetMangleRules(false);
        $this->assertEquals(count($html->getMangleRules()), count($default_rules));
        $html->resetMangleRules(true);
        $this->assertEmpty($html->getMangleRules());
        $html->resetMangleRules();
        $this->assertNotEmpty($html->getMangleRules());
        $this->assertEquals(count($html->getMangleRules()), count($default_rules));
    }

    public function testHtml() {
        $html = new GtkdocHtml(@file_get_contents('AdgGtkLayout.html'));
        $this->assertEquals($html->getHtml(), '');
        $this->assertTrue($html->process());
        $this->assertStringEqualsFile('AdgGtkLayout.mangled', $html->getHtml());
    }

    public function testDescription() {
        $html = new GtkdocHtml(@file_get_contents('AdgGtkLayout.html'));
        $this->assertEquals($html->getDescription(), '');
        $this->assertTrue($html->process());
        $this->assertEquals($html->getDescription(),
                            'AdgGtkLayout — A scrollable AdgGtkArea based widget');
    }
}
