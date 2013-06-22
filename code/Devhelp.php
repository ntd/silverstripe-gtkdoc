<?php

class Devhelp {

    // The .devhelp2 content
    private $_xml;

    // The most recently generated TOC tree.
    private $_tree;


    private function _processDocument(DOMDocument $doc) {
        $toc = array(
            '' => array(
                'name' => '',
                'link' => ''
        ));

        foreach ($doc->getElementsByTagName('sub') as $sub) {
            $link = $sub->getAttribute('link');
            $name = $sub->getAttribute('name');
            $parent = &$toc[$sub->parentNode->getAttribute('link')];
            $toc[$link] = array(
                'name'   => $name,
                'link'   => $link,
                'parent' => &$parent
            );
            $parent['sub'][] = &$toc[$link];
        }

        $this->_tree =& $toc;
    }


    /**
     * Create a new Devhelp instance.
     *
     * A string containing the XML of the .devhelp2 file to parse must
     * be passed in. The real processing will be triggered by the
     * process() method.
     *
     * Parsing a file can be easily performed by using
     * file_get_contents():
     *
     * <code>
     * $devhelp = new Devhelp(@file_get_content($file));
     * </code>
     *
     * @param String $xml A chunk of valid XML (UTF-8 encoded).
     */
    function __construct($xml) {
        $this->_xml = $xml;
    }

    /**
     * Parse and process the XML.
     *
     * The TOC tree is built from the passed in XML. Without a call to
     * process(), getTOC() always returns null 
     *
     * @return boolean true on success, false on errors.
     */
    public function process() {
        // Check if $this->_xml is valid
        if (! is_string($this->_xml) || empty($this->_xml))
            return false;

        // Check silverstripe-autotoc for details on $prefix
        $prefix = "<?xml encoding=\"utf-8\" ?>\n";

        // Parse the XML into a DOMDocument tree
        $doc = new DOMDocument();
        if (! @$doc->loadHTML($prefix . $this->_xml))
            return false;

        // Process the doc
        $this->_processDocument($doc);
        return true;
    }

    /**
     * Get the devhelp TOC.
     *
     * The XML of the .devhelp2 file must be fed from the constructor.
     *
     * @return Array An array representing the TOC. A valid array is
     *               always returned.
     */
    public function getTOC() {
        return is_array($this->_tree) ? $this->_tree : array();
    }
}
