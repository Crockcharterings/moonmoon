<?php
/*
Copyright (c) 2006, Maurice Svay
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

* Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.
* Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
* Neither the name of Maurice Svay nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED
TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER
OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

/**
 * Planet, main app class
 */
class Planet
{
    public $config;
    public $items;
    public $people;
    public $errors;
    private $storage;

    public function __construct($config=null, $storage=null)
    {

        if ($config == null) {
            $this->config = new PlanetConfig(array());
        } else {
            $this->config = $config;
        }

        $this->items  = array();
        $this->people = array();
        $this->errors = array();
        $this->storage = $storage;
    }

    /**
     * Getters
     */
    public function getItems()
    {
        $this->items = $this->_filterItemsByCategory(
            $this->items,
            $this->config->getCategories());

        return $this->items;
    }

    public function getPeople()
    {
        return $this->people;
    }

    /**
     * Adds a feed to the planet
     * @param PlanetFeed feed
     */
    public function addPerson(&$feed)
    {
        $this->people[] = $feed;
    }

    /**
     * Load people from an OPML
     * @return integer Number of people loaded
     */
    public function loadOpml($file)
    {
        if (!is_file($file)) {
            $this->errors[] = new PlanetError(3, $file.' is missing.');
            return 0;
        }

        $opml = OpmlManager::load($file);
        $opml_people = $opml->getPeople();
        foreach ($opml_people as $opml_person){
            $this->addPerson(
                new PlanetFeed(
                    $opml_person['name'],
                    $opml_person['feed'],
                    $opml_person['website']
                )
            );
        }
        return count($opml_people);
    }

    /**
     * Load feeds from cache
     */
    public function loadFeeds()
    {
        $simplepie_items = array();

        //Load items from Simplepie cache
        if ('sqlite' !== $this->config->storage) {
            foreach ($this->people as $feed) {
                $feed->init();
                $feed->set_timeout(0);
                $simplepie_items = array_merge($simplepie_items, $feed->get_items());
            }

            //Convert Simplepie_Item to PlanetItem
            foreach ($simplepie_items as $item) {
                $planet_item = new PlanetItem();
                $planet_item->initFromSimplepieItem($item, $item->get_feed());
                $this->items[] = $planet_item;
            }
            $this->sort();
        } else {
            $this->items = $this->storage->getAll();

            //Link an item to its feed
            //@FIXME: should be done in PlanetItemStorage
            foreach ($this->items as $item) {
                foreach ($this->people as $feed) {
                    if ($item->feedUrl == $feed->feed_url) {
                        $item->set_feed($feed);
                    }
                }
            }
        }

    }

    /**
     * Download feeds from source
     */
    public function download()
    {
        foreach ($this->people as $feed) {
            $feed->init();
            if ($feed->get_item_quantity() > 0){
                $items = $feed->get_items();
                $this->items = array_merge($this->items, $items);

                //Storage
                if ($this->storage) {
                    foreach ($items as $item) {
                        $this->storage->save($item);
                    }
                }

            } else {
                $this->errors[] = new PlanetError(1, 'No items : '.$feed->getFeed());
            }
        }
    }

    public function sort()
    {
        usort($this->items, array('PlanetItem','compare'));
    }

    /**
     * Filter out items that do not match at least one
     * of the defined categories.
     *
     * If there's no category, return all items.
     *
     * @param array  $items to filter
     * @param string $categories to filter against; may be a single word
     * or a comma-separated list of words.
     *
     * @return array resulting list of items
    */
    public function _filterItemsByCategory($items, $categories = null)
    {
        $categories = trim($categories);

        if (empty($categories))
            return $items;

        $categories         = array_map('trim', explode(',', strtolower($categories)));
        $cb_category_filter =
            function ($item) use ($categories)
            {
                if (!is_array($item_categories = $item->get_categories()))
                    return false;

                $item_categories = array_map(
                    function ($i) { return strtolower($i->get_label()); },
                    $item_categories
                );

                return array_intersect($categories, $item_categories);
            };

        return array_values(array_filter($items, $cb_category_filter));
    }
}
