<?php

namespace Sanjab\Helpers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;

/**
 * @method $this url (string $value)        url of menu item.
 * @method $this title (string $value)      title of menu.
 * @method $this icon (string $value)       icon of menu.
 * @method $this active (callable $value)   callback to check this item is active or not.
 * @method $this hidden (callable $value)   callback to hide or show.
 * @method $this target (string $value)     menu target.
 * @method $this order (int $value)         order of menu item.
 * @method $this badge (mixed $val)         badge to show beside menu value or callback.
 * @method $this badgeVariant (string $val) bootstrap badge variant.
 */
class MenuItem extends PropertiesHolder
{
    protected $properties = [
        'icon' => 'code',
        'title' => 'TITLE HERE',
        'badgeVariant' => 'danger',
        'order' => 100,
    ];

    /**
     * Children inside menu item.
     *
     * @var MenuItem[]
     */
    protected $children = [];

    /**
     * Check menu item is active or not.
     *
     * @return bool
     */
    public function isActive()
    {
        if (is_callable($this->property('active'))) {
            return $this->property('active')();
        }
        if (! empty($this->property('url'))) {
            return Request::is(trim(parse_url($this->property('url'))['path'], '/'));
        }
    }

    /**
     * Check menu item is hidden or not.
     *
     * @return bool
     */
    public function isHidden()
    {
        if (isset($this->properties['hidden'])) {
            return App::call($this->properties['hidden']);
        }

        return false;
    }

    /**
     * Get children of item.
     *
     * @return array|MenuItem[]
     */
    public function getChildren()
    {
        return array_filter($this->children, function ($menuItem) {
            return ! $menuItem->isHidden();
        });
    }

    /**
     * Get children of item including hidden children.
     *
     * @return array|MenuItem[]
     */
    public function getAllChildren()
    {
        return $this->children;
    }

    /**
     * Check has children.
     *
     * @return bool
     */
    public function hasChildren()
    {
        return count($this->children) > 0;
    }

    /**
     * Add a child item inside.
     *
     * @param MenuItem $childItem
     * @return $this
     */
    public function addChild(self $childItem)
    {
        $this->children[] = $childItem;

        return $this;
    }

    /**
     * Add multiple child.
     *
     * @param array|MenuItem[]  $childItems
     * @return $this
     */
    public function addChildren(array $childItems)
    {
        $this->children = array_merge($this->children, $childItems);

        return $this;
    }

    /**
     * Get badge value.
     *
     * @return mixed
     */
    public function getBadgeValue()
    {
        $badge = $this->property('badge');
        if (is_callable($badge)) {
            $badge = $badge();
            $this->setProperty('badge', $badge);
        }

        return $badge;
    }

    /**
     * create new Menu item.
     *
     * @property string $url  url of menu item.
     * @return static
     */
    public static function create($url = null)
    {
        $out = new static;
        if ($url) {
            $out->url($url);
        }

        return $out;
    }
}
