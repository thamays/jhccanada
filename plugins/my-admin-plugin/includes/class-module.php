<?php
/** Base class for every section of the admin panel. */
abstract class JHC_Module {
    abstract public function slug();            // url segment, e.g. "life-members"
    abstract public function label();           // sidebar + page title
    public function icon()  { return '•'; }
    public function install() {}                // create tables, import legacy data
    public function stats() { return null; }    // ['count' => int, 'label' => string] for the dashboard
    public function recent($n = 5) { return []; } // [['title'=>, 'meta'=>, 'url'=>]] for the dashboard
    public function quick_actions() { return []; } // [['label'=>, 'url'=>]]
    /** Handle the request and echo the page body (inside the panel layout). */
    abstract public function dispatch();
}
