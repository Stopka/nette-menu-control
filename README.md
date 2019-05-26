# Nette menu control
Nette control for rendering simple static menus

## Instalation
Add library dependency using composer:
```bash
composer require stopka/nette-menu-control:~1.0.0 
```

## Usage
Register submenu factory to DI container:
```neon
services:
    - Stopka\NetteMenuControl\MenuFactory
```

Create your menu factory:
```php
use Stopka\NetteMenuControl\ISubmenuFactory;

class MainMenuFactory {
    /** @var ISubmenuFactory */
    private $submenuFactory;
    
    function __create(ISubmenuFactory $submenuFactory){
        $this->submenuFactory = $submenuFactory;
    }
    
    public function create(): Menu{
        $menu = $this->submenuFactory->createMenu("Home",'Homepage:default');
        $menu->addSubmenu("Some item","Presenter:view");
        // build menu as you need...
        return $menu;
    }
}
```
Register your factory also to DI Container
```neon
services:
    - Stopka\NetteMenuControl\MenuFactory
    - MainMenuFactory
```
