<?php

require_once '../code/GtkdocSection.php';

class GtkdocSectionTest extends PHPUnit_Framework_TestCase {

    public function testProcess() {
        $section = new GtkdocSection(1234);
        $this->assertFalse($section->process());

        $section = new GtkdocSection('');
        $this->assertFalse($section->process());

        $section = new GtkdocSection(null);
        $this->assertFalse($section->process());

        $section = new GtkdocSection(array('1234'));
        $this->assertFalse($section->process());

        $section = new GtkdocSection('1234');
        $this->assertTrue($section->process());
    }

    public function testMangleRules() {
        $section = new GtkdocSection(@file_get_contents('AdgGtkLayout.html'));
        $default_rules = $section->getMangleRules();
        $this->assertNotEmpty($default_rules);
        $section->resetMangleRules(true);
        $this->assertEmpty($section->getMangleRules());
        $section->addMangleRules(array('a', 'dummy' => 'b'));
        $this->assertEquals(2, count($section->getMangleRules()));
        $section->addMangleRules(array('key' => 'value'));
        $rules = $section->getMangleRules();
        $this->assertEquals(3, count($rules));
        $this->assertEquals(key($rules), 'key');
        $this->assertEquals(current($rules), 'value');
        $section->addMangleRules(array('key' => 'other value'));
        $rules = $section->getMangleRules();
        $this->assertEquals(3, count($rules));
        $this->assertEquals(key($rules), 'key');
        $this->assertEquals(current($rules), 'other value');
        $section->addMangleRules(array('dummy' => 'b again'));
        $rules = $section->getMangleRules();
        $this->assertEquals(3, count($rules));
        $this->assertEquals(key($rules), 'dummy');
        $this->assertEquals(current($rules), 'b again');
        $section->resetMangleRules(false);
        $this->assertEquals(count($section->getMangleRules()), count($default_rules));
        $section->resetMangleRules(true);
        $this->assertEmpty($section->getMangleRules());
        $section->resetMangleRules();
        $this->assertNotEmpty($section->getMangleRules());
        $this->assertEquals(count($section->getMangleRules()), count($default_rules));
    }

    public function testHtml() {
        $section = new GtkdocSection(@file_get_contents('AdgGtkLayout.html'));
        $this->assertEquals($section->getHtml(), '');
        $this->assertTrue($section->process());
        $this->assertStringEqualsFile('AdgGtkLayout.section', $section->getHtml());
    }

    public function testDescription() {
        $section = new GtkdocSection(@file_get_contents('AdgGtkLayout.html'));
        $this->assertEquals($section->getDescription(), '');
        $this->assertTrue($section->process());
        $this->assertEquals($section->getDescription(),
                            'AdgGtkLayout — A scrollable AdgGtkArea based widget');
    }
}
