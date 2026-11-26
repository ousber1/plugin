<?php
$this->layout('installer/layout', ['step' => $step, 'steps' => $steps, 'state' => $state]);
echo $this->insert('installer/steps/' . $steps[$step], get_defined_vars());
