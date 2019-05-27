<?php

namespace Stopka\NetteMenuControl;

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
class Menu extends Control
{

    const LINK_PARAM_PROCESSOR_ALL = '*';

    /** @var ITranslator|null */
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
    private $show = true;

    /** @var  mixed */
    protected $linkParamValue;

    /** @var  null|\string */
    protected $linkParamName;

    /** @var  null|\string */
    protected $linkParamNeeded;

    /** @var  callable[] */
    protected $linkParamPreprocesors = [];

    /** @var bool */
    protected $authorizationSet = false;

    /** @var null|string */
    protected $authorizationResource = null;

    /** @var null|string */
    protected $authorizationPrivilege = null;

    /** @var callback|string */
    protected $class;

    /** @var bool */
    protected $beforeRenderCalled = false;

    /** @var ISubmenuFactory */
    protected $submenuFactory;

    /**
     * App\Controls\Menus\Menu item constructor.
     * @param ISubmenuFactory $submenuFactory
     * @param ITranslator $translator
     * @param string|callable|null $link url, nette link or callback
     * @param array $linkArgs
     * @param string $title
     */
    public function __construct(
        ISubmenuFactory $submenuFactory,
        ?ITranslator $translator,
        string $title,
        $link = null,
        array $linkArgs = []
    ) {
        $this->translator = $translator;
        $this->link = $link;
        $this->linkArgs = $linkArgs;
        $this->title = $title;
        $this->submenuFactory = $submenuFactory;
        $this->monitor(IPresenter::class, function () {
            $this->setActiveByPresenter();
        });
    }

    /**
     * Generates original subcomponent name automatically
     * @return string
     */
    private function generateSubcomponentName(): string
    {
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
     * Adds next menu item as a child
     * @param string|callable|null $link
     * @param array $linkArgs
     * @param \string $title
     * @param \string $name
     * @return self
     */
    public function addSubmenu(string $title, $link = null, array $linkArgs = [], $name = null): self
    {
        if ($name === null) {
            $name = $this->generateSubcomponentName();
        }
        $menu = $this->submenuFactory->createSubmenu($title, $link, $linkArgs);
        $this->addComponent($menu, $name);
        return $menu;
    }

    /**
     * Creates instance of menu createed by factory
     * @param IMenuFactory $factory
     * @param \string|null $name
     * @return self
     */
    public function addSubmenuFromFactory(IMenuFactory $factory, ?string $name = null): self
    {
        if ($name === null) {
            $name = $this->generateSubcomponentName();
        }
        $menu = $factory->create();
        $this->addComponent($menu, $name);
        return $menu;
    }

    /**
     * Returns parent menu item for back button
     * @return Menu|null
     */
    public function findUpper(): ?self
    {
        $currents = $this->findCurrent();
        if (!$currents) {
            return null;
        }
        $url = $currents[0]->getUrl();
        $path = $currents[0]->getPath();
        if (count($path) == 1) {
            return null;
        }
        $nodeUrl = null;
        for ($i = count($path) - 2; $i > 0; $i--) {
            $node = $path[$i];
            $nodeUrl = $node->getUrl();
            if ($node->currentable && $nodeUrl && $nodeUrl != $url) {
                return $node;
            }
        }
        if ($nodeUrl == $url) {
            return null;
        }
        return $path[0];
    }

    /**
     * @throws MenuException
     */
    public function render(): void
    {
        $this->renderTree();
    }

    public function renderUpper(): void
    {
        $node = $this->findUpper();
        if (!$node) {
            return;
        }
        $html = $node->buildUpperHtml();
        $this->renderHtml($html);
    }

    public function buildUpperIcon(): Html
    {
        return Html::el("i", ['class' => 'upper-icon']);
    }

    public function buildUpperHtml(): Html
    {
        return Html::el('div', $this->buildItemAttributes())
            ->addHtml($this->buildItemHtml(true, $this->buildUpperIcon()));
    }

    protected function buildItemIconHtml(): Html
    {
        if ($this->getIcon()) {
            return Html::el('i', [
                'class' => $this->getIcon()
            ]);
        }
        return Html::el();
    }

    protected function buildItemTitleHtml(): Html
    {
        return Html::el('span')
            ->addText($this->getTitle());
    }

    protected function buildItemInnerHtml(): Html
    {
        $html = Html::el();
        $html->addHtml($this->buildItemIconHtml());
        $html->addHtml($this->buildItemTitleHtml());
        return $html;
    }

    protected function buildLinkHtml(): Html
    {
        return Html::el('a', [
            'href' => $this->getUrl()
        ]);
    }

    protected function buildNonLinkHtml(): Html
    {
        return Html::el('span');
    }

    protected function buildItemHtml(bool $showLink = true, ?Html $prependHtml = null, ?Html $appendHtml = null): Html
    {
        $html = null;
        if ($showLink && $url = $this->getUrl()) {
            $html = $this->buildLinkHtml();
        } else {
            $html = $this->buildNonLinkHtml();
        }
        if ($prependHtml) {
            $html->addHtml($prependHtml);
        }
        $html->addHtml($this->buildItemInnerHtml());

        if ($appendHtml) {
            $html->addHtml($appendHtml);
        }
        return $html;
    }

    private function renderHtml(Html $html)
    {
        echo $html->render();
    }

    public function renderItem(bool $showLink = true)
    {
        /** @noinspection PhpDeprecationInspection */
        $this->callBeforeRender();
        $html = $this->buildItemHtml($showLink);
        $this->renderHtml($html);
    }

    /**
     * @param string $rootName
     * @throws MenuException
     */
    public function renderTree(string $rootName = null)
    {
        /** @noinspection PhpDeprecationInspection */
        $this->callBeforeRender();
        $template = $this->getTemplate();
        $template->setFile(__DIR__ . '/Tree.latte');
        if ($rootName == null) {
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

    public function renderSubtree()
    {
        /** @noinspection PhpDeprecationInspection */
        $this->callBeforeRender();
        $template = $this->getTemplate();
        $template->setFile(__DIR__ . '/Subtree.latte');
        /** @noinspection PhpUndefinedFieldInspection */
        $template->node = $this;
        $template->render();
    }

    protected function buildListHtml(): Html
    {
        return Html::el('ul', [
            'class' => $this->getClass()
        ]);
    }

    /**
     * @return array of attribute => [values]
     */
    protected function buildItemAttributes(): array
    {
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
        return [
            'class' => $classes
        ];
    }

    protected function buildListItemHtml(): Html
    {
        $html = Html::el('li', $this->buildItemAttributes());
        $html->addHtml($this->buildItemHtml());
        return $html;
    }

    protected function buildPathHtml(): Html
    {
        $html = $this->buildListHtml();
        /** @noinspection PhpUndefinedMethodInspection */
        $html->addClass('path');
        $pathNodes = $this->getCurrentPath();
        foreach ($pathNodes as $node) {
            $html->addHtml($node->buildListItemHtml());
        }
        return $html;
    }

    public function renderPath()
    {
        /** @noinspection PhpDeprecationInspection */
        $this->callBeforeRender();
        $html = $this->buildPathHtml();
        $this->renderHtml($html);
    }

    protected function buildChildrenHtml(): Html
    {
        $html = $this->buildListHtml();
        foreach ($this->getChildren() as $node) {
            if (!$node->isVisible()) {
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
    public function renderChildren(string $nodeName = null)
    {
        /** @noinspection PhpDeprecationInspection */
        $this->callBeforeRender();
        if ($nodeName == null) {
            $node = $this;
        } else {
            $node = $this->getDeepMenuComponent($nodeName);
        }
        $this->renderHtml($node->buildChildrenHtml());
    }

    /**
     * @param string $name
     * @param bool $needed
     * @return Menu|null
     * @throws MenuException
     */
    public function getDeepMenuComponent(string $name, bool $needed = true): ?Menu
    {
        $all = $this->getComponents(true, Menu::class);
        foreach ($all as $one) {
            if ($one->getName() == $name) {
                return $one;
            }
        }
        if ($needed) {
            throw new MenuException("Component not found");
        }
        return null;
    }

    /**
     * Is link url string?
     * @return bool
     */
    public function hasDirectUrl(): bool
    {
        if (is_string($this->link) && (substr($this->link, 0, 7) === "http://" || substr($this->link, 0,
                    8) === "https://")) {
            return true;
        }
        return false;
    }

    /**
     * Generates url from link params if possible
     * @return null|string
     */
    public function getUrl(): ?string
    {
        if ($this->link === null) {
            return null;
        }
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
     * @param callable|string|null $link
     * @return $this
     */
    public function setLink($link): self
    {
        $this->link = $link;
        return $this;
    }

    /**
     * @param callable|string $title
     * @return $this
     */
    public function setTitle($title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Returns translated title
     * @return \string
     */
    public function getTitle(): string
    {
        if (!$this->translator || $this->disableTranslation) {
            return $this->title;
        }
        return $this->translator->translate($this->title);
    }

    /**
     * @param ITranslator|null $translator
     * @return $this
     */
    public function setTranslator(?ITranslator $translator): self
    {
        $this->translator = $translator;
        return $this;
    }

    /**
     * @param bool $disable
     * @return $this
     */
    public function disableTranslation(bool $disable = true): self
    {
        $this->disableTranslation = $disable;
        return $this;
    }

    /**
     * Children of current menu item
     * @param bool $deep
     * @return \Iterator|self[]
     */
    public function getChildren($deep = false): array
    {
        $result = [];
        foreach ($this->getComponents($deep, self::class) as $component) {
            $result[] = $component;
        }
        return $result;
    }

    /**
     * Sets if item is visible in menu
     * @param callback|string $show
     * @return $this
     */
    public function setShow($show): self
    {
        $this->show = $show;

        return $this;
    }

    /**
     * Checks if item is visible (even by controling permissions)
     * @return \bool
     */
    public function getShow(): bool
    {
        if (is_callable($this->show)) {
            return (boolean)call_user_func($this->show, $this);
        }
        return $this->show;
    }

    /**
     * Checks if is allowed and is shown
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this->getShow() && $this->isAllowed();
    }

    /**
     * Item needs parameter to generate url link
     * @param null|string $key
     * @param null|mixed $defaultValue
     * @param string $paramName
     * @return $this
     */
    public function setLinkParamNeeded(?string $key = null, $defaultValue = null, string $paramName = 'id'): self
    {
        $this->linkParamNeeded = $key;
        $this->linkParamValue = $defaultValue;
        $this->linkParamName = $paramName;
        return $this;
    }

    /**
     * Sets css class
     * @param callback|string $class
     * @return $this
     */
    public function setClass($class): self
    {
        $this->class = $class;
        return $this;
    }

    /**
     * Sets icon
     * @param \string|null $class
     * @return $this
     */
    public function setIcon(?string $class): self
    {
        $this->icon = $class;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * Css class of item
     * @return \string|null
     */
    public function getClass(): ?string
    {
        if (is_callable($this->class)) {
            return call_user_func($this->class, $this);
        }
        return $this->class;
    }

    /**
     * Is this menu item's link your actual position on the web?
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Is this menu item your actual position on the web?
     * @return bool
     */
    public function isCurrent(): bool
    {
        return ($this->currentable && $this->isActive());
    }

    /**
     * Sets if this item can ever be actual position on the web
     * @param bool $bool
     * @return $this
     */
    public function setCurentable(bool $bool = true): self
    {
        $this->currentable = $bool;
        return $this;
    }

    /**
     * Returns path of items back to the root menu
     * @return self[]
     */
    public function getPath(): array
    {
        $path = Array($this);
        $parent = $this->getParentMenu();
        if ($parent) {
            $path = array_merge($parent->getPath(), $path);
        }
        return $path;
    }

    /**
     * Searches subtree for menu items marked as current
     * @return self[]
     */
    public function findCurrent(): array
    {
        return $this->find(function (Menu $node) {
            return $node->isCurrent();
        });
    }

    /**
     * Serches subtree of components by calback criteria
     * @param callback $check
     * @return self[]
     */
    protected function find($check): array
    {
        $result = Array();
        foreach ($this->getChildren(true) as $child) {
            if ((boolean)call_user_func($check, $child)) {
                $result[] = $child;
            }
        }
        return $result;
    }

    /**
     * Returns path from root to first item marked as current
     * @return self[]
     */
    public function getCurrentPath(): array
    {
        $node = $this->findCurrent();
        if (count($node) == 0) {
            return Array($this);
        }
        return $node[0]->getPath();
    }

    /**
     * Sets item as active
     * @param \bool $value
     * @return $this;
     * @internal
     */
    public function setActive(bool $value = true): self
    {
        $this->active = $value;
        return $this;
    }

    public function getParentMenu(): ?self
    {
        /** @var self $parent */
        $parent = $this->getParent();
        if (!is_a($parent, self::class)) {
            return null;
        }
        return $parent;
    }

    /**
     * Sets in path flag,
     * this flag can be set also to parent item automatically
     * @param bool $value
     * @param bool|int $toParent how many layers of parents, true means all
     * @return $this
     * @internal
     */
    public function setInPath(bool $value = true, $toParent = true): self
    {
        $this->inPath = $value;
        if ($toParent) {
            $parent = $this->getParentMenu();
            if ($parent) {
                $parent->setInPath(true, $toParent);
            }
        }
        return $this;
    }

    /**
     * Is item in path of your current position on the web
     * @param bool $currentableOnly only if is currentable
     * @return bool
     */
    public function isInPath(bool $currentableOnly = false)
    {
        if ($currentableOnly && !$this->currentable) {
            return false;
        }
        return $this->inPath;
    }

    /**
     * Automatically sets current, active and inPath flags using presenter
     * @throws MenuException
     */
    public function setActiveByPresenter(): void
    {
        if ($this->link === null) {
            $this->setActive(false);
        } else {
            if ($this->hasDirectUrl()) {
                $this->setActive(false);
            } else {
                if (is_callable($this->link)) {
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
    public function setLinkParam(string $key, $value)
    {
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
    public function setLinkParamPreprocessor(
        string $key = self::LINK_PARAM_PROCESSOR_ALL,
        ?callable $callback = null
    ): self {
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
    public function setAuthorization(?string $resource = null, ?string $privilege = null): self
    {
        $this->authorizationResource = $resource;
        $this->authorizationPrivilege = $privilege;
        $this->authorizationSet = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function resetAuthorization(): self
    {
        $this->authorizationResource = null;
        $this->authorizationPrivilege = null;
        $this->authorizationSet = false;
        return $this;
    }


    /**
     * Checks if user is authorized to view this item
     * @return \bool
     */
    public function isAllowed(): bool
    {
        if (!$this->authorizationSet) {
            return true;
        }
        return $this->getPresenter()
            ->getUser()
            ->isAllowed($this->authorizationResource, $this->authorizationPrivilege);
    }

    protected function callBeforeRender()
    {
        if ($this->beforeRenderCalled) {
            return;
        }
        $this->beforeRenderCalled = true;
        /** @noinspection PhpDeprecationInspection */
        $this->beforeRender();
    }

    /**
     * @deprecated
     */
    protected function beforeRender()
    {

    }

    /**
     * @return self
     */
    public function getMenuRoot(): self
    {
        $parent = $this->getParent();
        if (!is_a($parent, self::class)) {
            return $this;
        }
        /** @var $parent self */
        return $parent->getMenuRoot();
    }

}
