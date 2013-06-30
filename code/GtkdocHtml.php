<?php

class GtkdocHtml {

    // The original HTML
    private $_raw_html;

    // The parsed HTML, suitable for inclusion in a bigger page
    private $_html;

    // The description for this section, if found in the original HTML
    private $_description;

    // The mangle table for _urlMangler()
    private $_mangle_table;


    private function _urlMangler($url) {
        // A single preg_replace() call with arrays cannot be used
        // because the replacement must stop at the first match
        $count = 0;
        foreach ($this->_mangle_table as $pattern => $replacement) {
            $url = preg_replace($pattern, $replacement, $url, 1, $count);
            if ($count > 0)
                break;
        }

        return $url;
    }

    private function _processDocument(DOMDocument $doc) {
        $xpath = new DOMXPath($doc);
        $result = $doc->createElement('div');
        $result->setAttribute('class', 'gtkdoc');

        // Set the description of this section, if possible
        $element = $xpath->query('//div[@class="refnamediv"]//h2/../p|//h3')->item(0);
        $element and $this->_description = trim($element->nodeValue);

        // Import valid elements into $result
        foreach ($xpath->query('body/div[@class="book" or @class="part" or @class="chapter" or @class="refentry" or @class="index" or @class="glossary"]') as $element) {
            $result->appendChild($element);
        }

        // No valid elements are found: fallback to the whole content of body
        if (! $result->hasChildNodes()) {
            foreach ($xpath->query('body/*') as $element)
                $result->appendChild($element);
        }

        // Remove invalid elements from the result: the helper
        // $elements array is used to avoid modifying the DOM
        // while iterating over it
        $elements = array();
        foreach ($xpath->query('(.//div[@class="titlepage"])[1]|.//div[@class="refnamediv"]', $result) as $element)
            $elements[] = $element;
        foreach ($elements as $element)
            $element->parentNode->removeChild($element);

        // Resolve href targets
        foreach ($xpath->query('.//a/@href', $result) as $element)
            $element->value = $this->_urlMangler($element->value);

        $this->_html = $doc->saveHTML($result);
    }


    /**
     * Create a new GtkdocHtml instance.
     *
     * A string containing the HTML of the gtkdoc file to parse must
     * be passed in. The real processing will be triggered by the
     * process() method.
     *
     * Parsing a file can be easily performed by using
     * file_get_contents():
     *
     * <code>
     * $html = new GtkdocHtml(@file_get_content($file));
     * </code>
     *
     * URL resolution is performed by a (customizable) mangle table,
     * that is the array returned by getMangleRules(). Every href is
     * matched against a serie of regex patterns (the keys of the mangle
     * table) and if matches it is substituted via preg_replace() with
     * the replacement text (the value of the mangle rule). This
     * substitution stop at the first match. If no rule matches, the
     * href value is left untouched.
     *
     * @param String $html A chunk of html (UTF-8 encoded).
     */
    function __construct($html) {
        $this->_raw_html = $html;
        $this->resetMangleRules();
    }

    /**
     * Initialize the mangle table to its default rules.
     *
     * Populates the internal mangle table with some (intrinsically
     * valid) rule, removing the custom ones eventually added with
     * addMangleRules().
     *
     * @param Boolean $all If true remove also the default rules.
     */
    public function resetMangleRules($all = false) {
        if ($all)
            $this->_mangle_table = array();
        else
            $this->_mangle_table = array(
                // cairo
                '|^.*/cairo/([^/]*)$|' => 'http://cairographics.org/manual/$1',

                // ADG canvas and CPML library
                '|^.*/adg/([^/]*)$|' => 'http://adg.entidi.com/adg/$1',
                '|^.*/cpml/([^/]*)$|' => 'http://adg.entidi.com/cpml/$1',

                // Try to resolve everything else to gnome.org
                '|^.*/([^/]*)/([^/]*)$|' => 'http://developer.gnome.org/$1/stable/$2'
            );
    }

    /**
     * Add one or more mangle rules.
     *
     * The rules must be passed in in an associative array with
     * regex patterns as keys and replacements as values, e.g.:
     *
     * <code>
     * $html->addMangleRules(array(
     *     '|/cairo/([^/]*)$|' => 'http://cairographics.org/manual/$1'
     * ));
     * </code>
     *
     * The precedence is significative: the last rule added has
     * precedence over the other ones.
     */
    public function addMangleRules($rules) {
        if (! is_array($rules))
            return false;

        $this->_mangle_table = $rules + $this->_mangle_table;
        return true;
    }

    /**
     * Return the mangle rules.
     *
     * Gets the internal mangle table, including the default rules.
     */
    public function getMangleRules() {
        return $this->_mangle_table;
    }

    /**
     * Convert the HTML.
     *
     * The original gtkdoc file is properly filtered and the href are
     * mangled to avoid having dead links everywhere.
     *
     * @param String $base_url The base url to prepend to local links.
     * @return boolean         true on success, false on errors.
     */
    public function process($base_url = '') {
        // Check if $this->_raw_html is valid
        if (! is_string($this->_raw_html) || empty($this->_raw_html))
            return false;

        // Check silverstripe-autotoc for details on $prefix
        $prefix = "<?xml encoding=\"utf-8\" ?>\n";

        // Parse the HTML into a DOMDocument tree
        $doc = new DOMDocument();
        $doc->strictErrorChecking = false;
        if (! @$doc->loadHTML($prefix . $this->_raw_html))
            return false;

        // Process the DOMDocument
        $this->Content = $this->_processDocument($doc);

        return true;
    }

    /**
     * Get the mangled HTML.
     *
     * Returns the processed HTML. The original gtkdoc text must be fed
     * from the constructor.
     *
     * @return String A valid (or empty) HTML chunk is always returned.
     */
    public function getHtml() {
        return is_string($this->_html) ? $this->_html : '';
    }

    /**
     * Get the description of this section.
     *
     * Returns a string describing this section. The description
     * is picked up from a specific tag text: it is likely a change
     * in the gtk-doc xsl will break this feature.
     *
     * @return String The description for this section.
     */
    public function getDescription() {
        return is_string($this->_description) ? $this->_description : '';
    }
}
