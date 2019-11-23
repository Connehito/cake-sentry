<?php
/**
 * @var \Cake\View\View $this
 */

foreach ($this->getVars() as $var) {
    var_dump([$var => $$var]);
}
