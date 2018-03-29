<?php
/**
 * Created by IntelliJ IDEA.
 * User: stopka
 * Date: 29.3.18
 * Time: 11:06
 */

namespace Stopka\NetteMenuControl;


interface ISubmenuFactory {
    public function createSubmenu(string $title, $link = null, array $linkArgs = []): Menu;
}