<?php

declare(strict_types=1);

// カスタムルールのオートロード
require_once __DIR__ . '/../vendor/autoload.php';

// カスタムルールのクラスをロード
$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->addPsr4('App\\PHPStan\\', __DIR__);
