<?php
/**
 * Created by IntelliJ IDEA.
 * User: stopka
 * Date: 29.3.18
 * Time: 11:06
 */

namespace Stopka\NetteMenuControl;


interface IMenuFactory
{
    public function create(): Menu;
}
