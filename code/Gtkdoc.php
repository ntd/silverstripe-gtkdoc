<?php

/**
 * Check the timestamp of a datetime field agaist a file.
 *
 * This function does not pertain to any class, so putting it here.
 *
 * @param  String  $datetime  A datetime field
 * @param  String  $file      The absolute path to the file name
 * @return Boolean            true if $datetime is newer than $file
 */
function _up_to_date($datetime, $file) {
    // If $datetime is not set, this is a new record
    // that *needs* to be updated
    $db_timestamp = strtotime($datetime);
    if (! is_int($db_timestamp))
        return false;

    // If $fs_timestamp is invalid, $file is probably unexistent.
    // Assume the model is up to date instead of updating its content
    // with a non-existent file.
    $fs_timestamp = @filemtime($file);
    if (! is_int($fs_timestamp))
        return true;

    return $fs_timestamp < $db_timestamp;
}


class Gtkdoc extends Page {

    static $icon = 'gtkdoc/images/gtkdoc';

    static $description = 'Incorporates into the pages tree an ebook generated by gtk-doc';

    static $allowed_children = array();

    static $db = array(
        'DevhelpFile' => 'Varchar(255)',
    );

    static $has_many = array(
        'Children'    => 'GtkdocSection'
    );

    // Cached copy of the Devhelp Table Of Contents
    private $_toc;


    /**
     * Adds an entry for the devhelp file.
     *
     * @return FieldList The new list of fields for the CMS.
     */
    function getCMSFields() {
        $fields = parent::getCMSFields();

        $field = new TextField('DevhelpFile', _t('GtkDoc.DEVHELP_FILE', 'Path to the .devhelp2 file'));
        $fields->addFieldToTab('Root.Main', $field, 'Content');

        return $fields;
    }

    /**
     * Get the TOC from the DevhelpFile
     *
     * The returned array will be cached in lookupNode().
     *
     * @return Array  The table of contents parsed from $DevhelpFile
     */
    public function getTOC() {
        $devhelp = new Devhelp(@file_get_contents($this->DevhelpFile));
        return $devhelp->process() ? $devhelp->getTOC() : array();
    }

    /**
     * Lookup a node in the Devhelp tree.
     *
     * The returned array will contain the following items:
     *
     * - name:   the name/title of the node
     * - link:   the link of this node, that is $filename
     * - parent: reference to the parent node (not set on the root node)
     * - sub:    the array of children nodes (optional)
     *
     * @param  String $filename The name of the gtk-doc file
     * @return Array|null       The node bound to $filename
     */
    public function lookupNode($filename) {
        if (! isset($this->_toc)) {
            $up_to_date = _up_to_date($this->LastEdited, $this->DevhelpFile);
            $lifetime = $up_to_date ? 3600 : -1;
            $this->_toc = $this->cacheToFile('getTOC', $lifetime, $this->DevhelpFile);
        }
        return @$this->_toc[$filename];
    }

    /**
     * Lookup a specific section.
     *
     * Gets the previously registered or creates a new one GtkdocSection
     * instance based from its filename.
     *
     * @param  String $filename   The name of the gtk-doc file
     * @return GtkdocSection|null The requested section
     */
    public function lookupSection($filename) {
        // Check if the root section has been requested
        if ($filename == '')
            return $this;

        // Check if there is a corresponding node in the devhelp tree
        $node = $this->lookupNode($filename);
        if (! $node)
            return null;

        // Get the section from the database or create a new (empty) one
        $section = GtkdocSection::fetchSection($this, $filename);
        $section or $section = new GtkdocSection(array(
            'RootID'     => $this->ID,
            'URLSegment' => $filename
        ));

        // Update the section, if needed
        $section->sync(@$node['name']);

        return $section;
    }

    /**
     * Return the parent of a specific section.
     *
     * The section is identified by its filename (the name of the
     * gtk-doc file, .html extension included), also called 'link' in
     * Devhelp.php. The root Gtkdoc has an empty $filename.
     *
     * @param  String $filename     The file name of the section
     * @return Gtkdoc|GtkdocSection The parent object
     */
    public function parentOfSection($filename) {
        // If we are on the root page, call the standard getParent()
        if ($filename == '')
            return $this->getParent();

        // The 'parent' field *must* be set or the node is invalid
        $node = $this->lookupNode($filename);
        if (! isset($node, $node['parent']))
            return null;

        return $this->lookupSection($node['parent']['link']);
    }

    /**
     * Return the children of a specific section.
     *
     * The section is identified by its filename (the name of the
     * gtk-doc file, .html extension included), also called 'link' in
     * Devhelp.php. The root Gtkdoc has an empty $filename.
     *
     * @param  String $filename The file name of the section
     * @return ArrayList        List of child pages or null on no children.
     */
    public function childrenOfSection($filename) {
        $node = $this->lookupNode($filename);
        if (is_null($node) || ! array_key_exists('sub', $node))
            return null;

        $children = new ArrayList();
        foreach ($node['sub'] as $node) {
            $child = $this->lookupSection($node['link']);
            $children->push($child);
        }

        return $children;
    }

    /**
     * Overrides Hierarchy::Children() implementation to use the
     * devhelp file to know the list of children.
     *
     * @return ArrayList List of child pages or null on no children.
     */
    public function Children() {
        return $this->childrenOfSection('');
    }
}

class GtkdocSection extends DataObject {

    // Needed to have searchable fields
    static $create_table_options = array(
        'MySQLDatabase' => 'ENGINE=MyISAM'
    );

    static $db = array(
        'URLSegment'      => 'Varchar(255)',
        'Title'           => 'Varchar(255)',
        'Content'         => 'HTMLText',
        'MetaDescription' => 'Text',
        'Sort'            => 'Int'
    );

    static $indexes = array(
        'URLSegment'      => true
    );

    static $has_one = array(
        'Root'            => 'Gtkdoc'
    );

    static $searchable_fields = array(
        'Title',
        'MetaDescription',
        'Content'
    );

    static $casting = array(
        'LastEdited'      => 'SS_Datetime',
        'Created'         => 'SS_Datetime',
        'MenuTitle'       => 'Text',
        'MetaTitle'       => 'Text',
        'MetaKeywords'    => 'Text',
        'ExtraMeta'       => 'Text',
        'ShowInMenus'     => 'Boolean',
        'ShowInSearch'    => 'Boolean',
        'Link'            => 'Text',
        'RelativeLink'    => 'Text',
        'AbsoluteLink'    => 'Text'
    );

    static $default_sort = '"Sort"';


    /**
     * Fetch the {@link GtkdocSection} object from the database.
     *
     * Looks up a gtk-doc section object from the database.
     *
     * @param  Gtkdoc|Int $root     The root Gtkdoc page or its ID
     * @param  String     $filename The name of the gtk-doc file
     * @return GtkdocSection|null   The section got from the database
     *                              or null if it is not found
     */
    static public function fetchSection($root, $filename) {
        $root_id = ($root instanceof Gtkdoc) ? $root->ID : $root;

        $filter  = '"RootID" = ' . ((int) $root_id) .
                   ' AND "URLSegment"=' . "'$filename'";

        return DataObject::get_one('GtkdocSection', $filter);
    }

    /**#@+
     * Page compatibility method.
     *
     * This method has been added to make GtkdocSection behaves as
     * a page (although it is not).
     */

    public function getMenuTitle() {
        return $this->Title;
    }

    public function getMetaTitle() {
        return $this->Title;
    }

    public function getMetaKeywords() {
        return null;
    }

    public function getExtraMeta() {
        return null;
    }

    public function getShowInMenus() {
        return false;
    }

    public function getShowInSearch() {
        return true;
    }

    public function Link() {
        return $this->Root->Link() . $this->URLSegment;
    }

    public function AbsoluteLink() {
        return $this->Root->AbsoluteLink() . $this->URLSegment;
    }

    public function RelativeLink() {
        return $this->Root->RelativeLink() . $this->URLSegment;
    }

    /**#@-*/

    /**
     * Path to the gtk-doc file.
     *
     * Returns the complete path to the HTML file generated by gtk-doc.
     *
     * @return String Path to the gtk-doc file.
     */
    public function getGtkdocFile() {
        $base_dir = dirname($this->Root->DevhelpFile);
        return $base_dir . DIRECTORY_SEPARATOR . $this->URLSegment;
    }

    /**
     * Check if the section data is newer than the gtk-doc file.
     *
     * If the page is not up to date, the database needs to be
     * synchronized with the file system by calling sync().
     *
     * The title needs to be passed in because it is defined in the
     * devhelp file, not in the gtk-doc file.
     *
     * @param  String $title The title of the section
     * @return Boolean       true if the page is up to date
     */
    public function up_to_date($title) {
        $up_to_date = _up_to_date($this->LastEdited, $this->GtkdocFile);
        return $title == $this->Title && $up_to_date;
    }

    /**
     * Synchronize the database with the gtk-doc file.
     *
     * $this->Root and $this->URLSegment *must* be set, otherwise sync()
     * will not know how to access the gtk-doc file.
     *
     * The title needs to be passed in because it is defined in the
     * devhelp file, not in the gtk-doc file.
     *
     * @param  String $title The title of the section
     * @param  Int    $sort  A sort index
     * @return Boolean       true on success, false on errors.
     */
    public function sync($title, $sort = null) {
        if ($this->up_to_date($title))
            return true;

        $html = new GtkdocHtml(@file_get_contents($this->GtkdocFile));
        $html->setBaseURL($this->RelativeLink());
        if (! $html->process())
            return false;

        isset($sort) or $sort = $this->Sort;

        $this->update(array(
            'Title'           => $title,
            'Content'         => $html->getHtml(),
            'MetaDescription' => $html->getDescription(),
            'Sort'            => $sort
        ));

        return $this->write() > 0;
    }

    /**
     * Get the root page.
     *
     * Returns the root Gtkdoc object this section belongs to.
     *
     * @return Gtkdoc|null The root Gtkdoc page or null on errors.
     */
    public function getRoot() {
        $root_id = $this->getField('RootID');
        if (! $root_id)
            return null;

        return DataObject::get_one('Gtkdoc', '"Gtkdoc"."ID" = ' . (int) $root_id);
    }

    /**
     * Get the parent of this section.
     *
     * Calls the root Gtkdoc object to get the parent of this section
     * from the .devhelp2 tree.
     *
     * @return ArrayList List of child pages or null on no children.
     */
    public function getParent() {
        return $this->Root->parentOfSection($this->URLSegment);
    }

    /**
     * Get the children of this section.
     *
     * Calls the root Gtkdoc object to get the children from the
     * .devhelp2 tree.
     *
     * @return ArrayList List of child pages or null on no children.
     */
    public function Children() {
        return $this->Root->childrenOfSection($this->URLSegment);
    }

    /**
     * Clear the gtkdoc cache table on /dev/build/.
     *
     * Pages are converted and stored into GtkdocSection on the first
     * access or when they are not up to date. For convenience, clear
     * this table on /dev/build/ too.
     */
    public function requireDefaultRecords() {
        parent::requireDefaultRecords();
        DB::query('TRUNCATE "' . __CLASS__ . '"');
        DB::alteration_message('GtkdocSection cleared', 'deleted');
    }
}

/**
 * Extends ContentController to include GtkdocSection matches in search
 * results.
 *
 * This extension is intentionally compatible with
 * ContentControllerSearchExtension (and in fact it substitutes it)
 * to skip the need of documenting it ;).
 */
class GtkdocControllerSearchExtension extends Extension {

    static private $search_action = '/home/SearchForm';

    static private function merge(&$matches, $possible_matches) {
        foreach ($possible_matches as &$possible_match) {
            $possible_url = $possible_match->URLSegment;
            $duplicate = false;
            foreach ($matches as &$match) {
                $url = $match->URLSegment;
                if ($possible_url == $url)
                    continue 2;
            }
            $matches[] = $possible_match;
        }
    }

    /**
     * Changes the search action.
     *
     * Redirects to the given URLs on a search. Check SearchForm()
     * for details.
     *
     * @param  String $action The new URL of the search action.
     */
    static public function setSearchAction($action) {
        self::$search_action = $action;
    }

    /**
     * Gets the search action.
     *
     * @return String The URL of the search action.
     */
    static public function getSearchAction() {
        return self::$search_action;
    }

    /**
     * Site search form.
     *
     * Similar to ContentControllerSearchExtension::SearchForm() but
     * redirecting to a suitable page to avoid invalid URLs such as
     * 'adg/AdgEntity.html/SearchForm'
     *
     * The default redirection is to '/home/SearchForm' but can be
     * changed with GtkdocControllerSearcExtension::setSearchAction().
     */
    public function SearchForm() {
        if ($this->owner->request && $this->owner->request->getVar('Search'))
            $searchText = $this->owner->request->getVar('Search');
        else
            $searchText =  _t('SearchForm.SEARCH', 'Search');

        $fields = new FieldList(
            new TextField('Search', false, $searchText)
        );

        $actions = new FieldList(
            new FormAction('results', _t('SearchForm.GO', 'Go'))
        );

        $form = new SearchForm($this->owner, 'SearchForm', $fields, $actions);
        $form->classesToSearch(FulltextSearchable::get_searchable_classes());

        // The following line is the reason of this overriding: we must
        // redirect to a suitable page to avoid invalid URLs such as
        // 'adg/AdgEntity.html/SearchForm'
        $form->setFormAction(self::$search_action);

        return $form;
    }

    /**
     * Gets the results from the given query.
     *
     * Appends to the usual results the matches on the GtkdocSection
     * instances on the following order:
     *
     * 1. GtkdocSections with matches in 'Title'
     * 2. GtkdocSections with matches in 'MetaDescription'
     * 3. GtkdocSections with matches in 'Content'
     *
     * Eventual duplicates are dropped from the list.
     *
     * @param array          $data    The raw data submitted by user
     * @param SearchForm     $form    The form instance
     * @param SS_HTTPRequest $request Request for this action
     */
    public function results($data, $form, $request) {
        $keywords = $data['Search'];

        // Populate the first matches in the usual way
        $matches = $form->getResults()->toArray();

        // Get the list of GtkdocSection with matching title...
        $context = singleton('GtkdocSection')->getDefaultSearchContext();
        $gtkdoc_matches = $context->getResults(array(
            'Title' => $keywords
        ))->toArray();

        // ...and add to them the list of GtkdocSection with matching
        // description and matching content, skipping the duplicates...
        self::merge($gtkdoc_matches,
                    $context->getResults(array(
                        'MetaDescription' => $keywords
                    ))->toArray());
        self::merge($gtkdoc_matches,
                    $context->getResults(array(
                        'Content' => $keywords
                    ))->toArray());

        // Append the GtkdocSection matches to the original ones
        $matches = array_merge($matches, $gtkdoc_matches);

        $results = new PaginatedList(new ArrayList($matches));
        $results->setPageLength(10);
        $results->setLimitItems(true);
        $results->setPageStart($request->getVar('start'));

        $data = array(
            'Results' => $results,
            'Query'   => $form->getSearchQuery(),
            'Title'   => _t('SearchForm.SearchResults', 'Search Results')
        );

        return $this->owner->customise($data)->renderWith(array('Page_results', 'Page'));
    }
}

class Gtkdoc_Controller extends Page_Controller {

    static $url_handlers = array(
        '$Name!' => 'section'
    );

    public function section($request) {
        $filename = $request->latestParam('Name') . '.html';
        $section = $this->lookupSection($filename);
        if (! $section)
            return $this->httpError(404, "Section '$filename' does not exist in devhelp '$this->DevhelpFile'");

        // Kind of a hack by I did not found a better way still
        // compatible with silverstripe-autotoc
        $this->dataRecord = $section;
        $this->Title = $section->Title;

        return $this->getViewer('section')->process($this);
    }
}
