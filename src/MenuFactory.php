<?php

namespace Stopka\NetteMenuControl;


use Nette\Localization\ITranslator;


/**
 * Strom menu
 *
 * @author stopka
 */
class MenuFactory implements ISubmenuFactory
{

    /** @var ITranslator */
    private $translator;

    public function __construct(?ITranslator $translator)
    {
        $this->translator = $translator;
    }

    public function createSubmenu(string $title, $link = null, array $linkArgs = []): Menu
    {
        return new Menu($this, $this->translator, $title, $link, $linkArgs);
    }
}


