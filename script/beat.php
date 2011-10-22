#!/usr/bin/env php
<?php
require_once __DIR__ . '/../src/Beat/Runner.php';
array_shift($argv);
call_user_func_array('Beat\Runner::run', $argv);
