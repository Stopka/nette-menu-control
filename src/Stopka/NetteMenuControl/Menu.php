<?php

namespace Stopka\NetteMenu;

use Nette\Application\IPresenter;
use Nette\Application\UI\Control;
use Nette\Application\UI\InvalidLinkException;
use Nette\Localization\ITranslator;
use Nette\Utils\Html;
use Nette\Utils\Strings;

/**
 * Strom menu
 *
 * @author stopka
 */
class Menu extends Control {

    const LINK_PARAM_PROCESSOR_ALL = '*';

    /** @var ITranslator */
    protected $translator;

    /** @var bool */
    protected $disableTranslation = false;

    /** @var \Nette\Security\User */
    protected $user;

    /** @var \string|callable */
    protected $link;

    /** @var array */
    protected $linkArgs;

    /** @var callable|string */
    protected $title;

    /** @var \bool */
    protected $active = false;

    /** @var \bool */
    protected $inPath = false;

    /** @var \bool */
    protected $currentable = true;

    /** @var  \string */
    protected $icon;

    /** @var callback|bool */
    private $show = TRUE;

    /** @var  mixed */
    protected $linkParamValue;

    /** @var  null|\string */
    protected $linkParamName;

    /** @var  null|\string */
    protected $linkParamNeeded;

    /** @var  callable[] */
    protected $linkParamPreprocesors = [];

    /** @var null|string */
    protected $authorizationResource = NULL;

    /** @var null|string */
    protected $authorizationPrivilege = NULL;

    /** @var callback|string */
    protected $class;

    /** @var bool */
    protected $beforeRenderCalled = false;

    /**
     * App\Controls\Menus\Menu item constructor.
     * @param ITranslator $translator
     * @param string|callable $link url, nette link or callback
     * @param array $linkArgs
     * @param string $title
     */
    public function __construct(?ITranslator $translator, string $title, $link, array $linkArgs = []) {
        parent::__construct();
        $this->translator = $translator;
        $this->link = $link;
        $this->linkArgs = $linkArgs;
        $this->title = $title;
    }

    /**
     * Generates original subcomponent name automatically
     * @return string
     */
    private function generateSubcomponentName(): string {
        $name = 'menu';
        $trial = $name;
        $count = 1;
        while ($this->getComponent($trial, false) != null) {
            $trial = $name . '_' . $count;
            $count++;
        }
        return $trial;
    }

    /**
     * @param $presenter
     * @throws MenuException
     */
    protected function attached($presenter) {
        parent::attached($presenter);
        if (is_subclass_of($presenter, IPresenter::class)) {
            $this->setActiveByPresenter();
        }
    }


    /**
     * Adds next menu item as a child
     * @param string|callable $link
     * @param array $linkArgs
     * @param \string $title
     * @param \string $name
     * @return Menu
     */
    public function add(string $title, $link, array $linkArgs = [], $name = NULL): Menu {
        if ($name === NULL) {
            $name = $this->generateSubcomponentName();
        }
        $result = new Menu($this->translator, $title, $link, $linkArgs);
        $this->addComponent($result, $name);
        return $result;
    }

    /**
     * Creates instance of class and adds it to the tree
     * @param \string $class class name
     * @param \string $name
     * @return Menu
     * @throws \Exception
     */
    public function addMenu($class, $name) {
        if (!is_subclass_of($class, Menu::class)) {
            throw new MenuException("$class is not subclass of App\Controls\Menus\Menu");
        }
        $result = new $class($this->translator, $this, $name);
        return $result;
    }

    /**
     * @throws MenuException
     */
    public function render() {
        $this->renderTree();
    }

    protected function buildItemIconHtml(): Html {
        if ($this->getIcon()) {
            return Html::el('i', [
                'class' => $this->getIcon()
            ]);
        }
        return Html::el();
    }

    protected function buildItemTitleHtml(): Html {
        return Html::el('span')
            ->addText($this->getTitle());
    }

    protected function buildItemInnerHtml(): Html {
        $html = Html::el();
        $html->addHtml($this->buildItemIconHtml());
        $html->addHtml($this->buildItemTitleHtml());
        return $html;
    }

    protected function buildLinkHtml(): Html {
        return Html::el('a', [
            'href' => $this->getUrl()
        ]);
    }

    protected function buildNonLinkHtml(): Html {
        return Html::el('span');
    }

    protected function buildItemHtml(bool $showLink = true): Html {
        $html = null;
        if ($showLink && $url = $this->getUrl()) {
            $html = $this->buildLinkHtml();
        } else {
            $html = $this->buildNonLinkHtml();
        }
        $html->addHtml($this->buildItemInnerHtml());
        return $html;
    }

    private function renderHtml(Html $html) {
        echo $html->render();
    }

    public function renderItem(bool $showLink = true) {
        $this->callBeforeRender();
        $html = $this->buildItemHtml($showLink);
        $this->renderHtml($html);
    }

    /**
     * @param string $rootName
     * @throws MenuException
     */
    public function renderTree(string $rootName = null) {
        $this->callBeforeRender();
        $template = $this->getTemplate();
        $template->setFile(__DIR__ . '/Tree.latte');
        if ($rootName == NULL) {
            /** @noinspection PhpUndefinedFieldInspection */
            $template->node = $this;
        } else {
            /** @var Menu $node */
            $node = $this->getComponent($rootName, false);
            if (!$node) {
                $node = $this->getDeepMenuComponent($rootName);
            }
            /** @noinspection PhpUndefinedFieldInspection */
            $template->node = $node;
        }
        $template->render();
    }

    public function renderSubtree() {
        $this->callBeforeRender();
        $template = $this->getTemplate();
        $template->setFile(__DIR__ . '/Subtree.latte');
        /** @noinspection PhpUndefinedFieldInspection */
        $template->node = $this;
        $template->render();
    }

    protected function buildListHtml(): Html {
        return Html::el('ul',[
            'class' => $this->getClass()
        ]);
    }

    protected function buildListItemHtml(): Html {
        $classes = [$this->getClass()];
        if ($this->isActive()) {
            $classes[] = 'active';
        }
        if ($this->isInPath()) {
            $classes[] = 'in-path';
        }
        if ($this->isCurrent()) {
            $classes[] = 'current';
        }
        $html = Html::el('li', [
            'class' => $classes
        ]);
        $html->addHtml($this->buildItemHtml());
        return $html;
    }

    protected function buildPathHtml(): Html {
        $html = $this->buildListHtml();
        /** @noinspection PhpUndefinedMethodInspection */
        $html->addClass('path');
        $pathNodes = $this->getCurrentPath();
        foreach ($pathNodes as $node) {
            $html->addHtml($node->buildListItemHtml());
        }
        return $html;
    }

    public function renderPath() {
        $this->callBeforeRender();
        $html = $this->buildPathHtml();
        $this->renderHtml($html);
    }

    protected function buildChildrenHtml():Html{
        $html = $this->buildListHtml();
        foreach ($this->getChildren() as $node){
            if(!$node->getShow()){
                continue;
            }
            $html->addHtml($node->buildListItemHtml());
        }
        return $html;
    }

    /**
     * @param string|NULL $nodeName
     * @throws MenuException
     */
    public function renderChildren(string $nodeName = NULL) {
        $this->callBeforeRender();
        if ($nodeName == NULL) {
            $node = $this;
        } else {
            $node = $this->getDeepMenuComponent($nodeName);
        }
        $this->renderHtml($node->buildChildrenHtml());
    }

    /**
     * @param $name
     * @return Menu
     * @throws MenuException
     */
    public function getDeepMenuComponent($name): Menu {
        $all = $this->getComponents(true, Menu::class);
        foreach ($all as $one) {
            if ($one->getName() == $name) {
                return $one;
            }
        }
        throw new MenuException("Component not found");
    }

    /**
     * Is link url string?
     * @return bool
     */
    public function hasDirectUrl(): bool {
        if (is_string($this->link) && (substr($this->link, 0, 7) === "http://" || substr($this->link, 0, 8) === "https://")) {
            return true;
        }
        return false;
    }

    /**
     * Vrací vygenerovanou URL
     * @return null|string
     */
    public function getUrl(): ?string {
        if (is_callable($this->link)) {
            return call_user_func($this->link);
        }
        if ($this->hasDirectUrl()) {
            return $this->link;
        }
        $args = $this->linkArgs;
        if ($this->linkParamNeeded !== null) {
            if ($this->linkParamValue == null) {
                return null;
            }
            $args[$this->linkParamName] = $this->linkParamValue;
        }
        try {
            $link = @$this->getPresenter()->link($this->link, $args);
        } catch (InvalidLinkException $e) {
            return null;
        }
        if (Strings::startsWith('#error', $link)) {
            return null;
        }
        return $link;
    }

    /**
     * Returns translated title
     * @return \string
     */
    public function getTitle() {
        if (!$this->translator || $this->disableTranslation) {
            return $this->title;
        }
        return $this->translator->translate($this->title);
    }

    /**
     * @param ITranslator|null $translator
     * @return $this
     */
    public function setTranslator(?ITranslator $translator) {
        $this->translator = $translator;
        return $this;
    }

    public function disableTranslation(bool $disable = true): self {
        $this->disableTranslation = $disable;
        return $this;
    }

    /**
     * Children of current menu item
     * @param bool $deep
     * @return \Iterator|Menu[]
     */
    public function getChildren($deep = FALSE) {
        return $this->getComponents($deep, Menu::class);
    }

    /**
     * Sets if item is visible in menu
     * @param callback|string $show
     * @return Menu
     */
    public function setShow($show) {
        $this->show = $show;

        return $this;
    }

    /**
     * Checks if item is visible (even by controling permissions)
     * @return \bool
     */
    public function getShow(): bool {
        if (!$this->isAllowed()) {
            return FALSE;
        }
        if (is_callable($this->show)) {
            return (boolean)call_user_func($this->show, $this);
        }
        return $this->show;
    }

    /**
     * Item needs parameter to generate url link
     * @param null|string $key
     * @param null|mixed $defaultValue
     * @param string $paramName
     * @return $this
     */
    public function setLinkParamNeeded(?string $key = null, $defaultValue = null, string $paramName = 'id') {
        $this->linkParamNeeded = $key;
        $this->linkParamValue = $defaultValue;
        $this->linkParamName = $paramName;
        return $this;
    }

    /**
     * Sets html class
     * @param callback|string $class
     * @return Menu
     */
    public function setClass($class) {
        $this->class = $class;
        return $this;
    }

    /**
     * Sets icon
     * @param \string $class
     * @return Menu $this
     */
    public function setIcon($class) {
        $this->icon = $class;
        return $this;
    }

    public function getIcon() {
        return $this->icon;
    }

    /**
     * Vrátí css třídu položky
     * @return \string
     */
    public function getClass() {
        if (is_callable($this->class)) {
            return call_user_func($this->class, $this);
        }
        return $this->class;
    }

    public function isActive() {
        return $this->active;
    }

    /**
     * Zda je položka vypisovatelná a aktivní
     * @return bool
     */
    public function isCurrent() {
        return ($this->currentable && $this->isActive());
    }

    /**
     * Nastaví příznak, zda může být použit v cestě jako aktivní
     * @param bool $bool
     * @return Menu
     */
    public function setCurentable($bool = true) {
        $this->currentable = $bool;
        return $this;
    }

    /**
     * Vrátí cestu App\Controls\Menus\Menu prvků od tohoto prvku ke kořeni
     * @return Menu[]
     */
    public function getPath() {
        $path = Array($this);
        if ($this->parent instanceof Menu) {
            $path = array_merge($this->parent->getPath(), $path);
        }
        return $path;
    }

    /**
     * Najde v podstromu aktivní prvky
     * @return Menu[]
     */
    public function findCurrent() {
        return $this->find(function (Menu $node) {
            return $node->isCurrent();
        });
    }

    /**
     * Najde v podstromu prvky odpovídající vyhodnocovacímu callbacku
     * @param callback $check
     * @return Menu[]
     */
    protected function find($check) {
        $result = Array();
        foreach ($this->getChildren(TRUE) as $child) {
            if ((boolean)call_user_func($check, $child)) {
                $result[] = $child;
            }
        }
        return $result;
    }

    /**
     * Vrátí cestu prvků k prvnímu aktivnímu prvku
     * @return Menu[]
     */
    public function getCurrentPath() {
        $node = $this->findCurrent();
        if (count($node) == 0) {
            return Array($this);
        }
        return $node[0]->getPath();
    }

    /**
     * Nastaví příznak aktivní položky
     * @param \bool $value
     */
    public function setActive($value = true) {
        $this->active = $value;
    }

    /**
     * Nastaví příznak položky obsažené v cestě
     * Příznak se nastaví i na rodičovi
     * @param bool $value
     * @param bool|int $set_parent je-li číslené, určiuje do jaké úrovně rodičů se má hodnota nastavit
     */
    public function setInPath($value = true, $set_parent = true) {
        $this->inPath = $value;
        if ($set_parent) {
            /** @var Menu $parent */
            $parent = $this->getParent();
            if (is_a($parent, "Elearning\Util\Menu\Menu")) {
                $set_parent = is_int($set_parent) ? $set_parent - 1 : true;
                $parent->setInPath($value, $set_parent);
            }
        }
    }

    /**
     * Vrátí zda je nastaven příznak, že je položka v cestě
     * @param bool $currentable_only zda se má počítat jen je-li položka currentable
     * @return bool
     */
    public function isInPath($currentable_only = false) {
        if ($currentable_only && !$this->currentable) {
            return false;
        }
        return $this->inPath;
    }

    /**
     * Nastaví autamaticky příznak aktivní položky a cesty v podstromu menu
     * @throws MenuException
     */
    public function setActiveByPresenter() {
        if ($this->hasDirectUrl()) {
            $this->setActive(false);
        } else {
            try {
                $is_current_link = $this->getPresenter()->isLinkCurrent($this->link);
            } catch (InvalidLinkException $e) {
                throw new MenuException("Unable to generate link", 0, $e);
            }
            $this->setActive($is_current_link);
            if ($is_current_link) {
                $this->setInPath($is_current_link);
            }
        }
        foreach ($this->getChildren() as $child) {
            $child->setActiveByPresenter();
        }
    }

    /**
     * Sets id param to all subitems
     * @param string $key
     * @param mixed $value
     */
    public function setLinkParam(string $key, $value) {
        if (isset($this->linkParamPreprocesors[$key])) {
            $value = call_user_func($this->linkParamPreprocesors[$key], $value, $key, $this);
        }
        if (isset($this->linkParamPreprocesors[self::LINK_PARAM_PROCESSOR_ALL])) {
            $value = call_user_func($this->linkParamPreprocesors[self::LINK_PARAM_PROCESSOR_ALL], $value, $key, $this);
        }
        if ($this->linkParamNeeded == $key) {
            $this->linkParamValue = $value;
        }
        foreach ($this->getChildren() as $child) {
            /** @var Menu $child */
            $child->setLinkParam($key, $value);
        }
    }

    /**
     * Sets preprocessor of the link param
     * @param string $key
     * @param callable|null $callback
     * @return $this
     */
    public function setLinkParamPreprocessor(string $key = self::LINK_PARAM_PROCESSOR_ALL, ?callable $callback) {
        if (!$callback) {
            unset($this->linkParamPreprocesors[$key]);
            return $this;
        }
        $this->linkParamPreprocesors[$key] = $callback;
        return $this;
    }

    /**
     * Sets authorization limits
     * @param null|string $resource
     * @param null|string $privilege
     * @return $this
     */
    public function setAuthorization(?string $resource = NULL, ?string $privilege = NULL): self {
        $this->authorizationResource = $resource;
        $this->authorizationPrivilege = $privilege;
        return $this;
    }


    /**
     * Zjistí zda má uživatel právo vidět položku
     * @return \bool
     */
    public function isAllowed() {
        return $this->getPresenter()
            ->getUser()
            ->isAllowed($this->authorizationResource, $this->authorizationPrivilege);
    }

    protected function callBeforeRender() {
        if ($this->beforeRenderCalled) {
            return;
        }
        $this->beforeRenderCalled = true;
        $this->beforeRender();
    }

    /**
     * Voláno před renderováním komponenty
     */
    protected function beforeRender() {

    }

}


