<?php

use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Tourze\AutoJsControlBundle\AutoJsControlBundle;
use Tourze\EasyAdminEnumFieldBundle\EasyAdminEnumFieldBundle;

/*
 * Bundle 注册示例
 *
 * 将以下内容添加到你的项目的 config/bundles.php 文件中：
 */

return [
    // ... 其他 bundles
    EasyAdminBundle::class => ['all' => true],
    EasyAdminEnumFieldBundle::class => ['all' => true],
    AutoJsControlBundle::class => ['all' => true],
];
