<?php
return array (
  '__meta' => 
  array (
    'signature' => 'cb11c6398edfdba82e2ebba4824b9d8f',
    'generated_at' => 1779507750,
  ),
  'test' => 
  array (
    'addons' => 
    array (
      'id' => 'test',
      'code' => 'test',
      'name' => '演示配置文件',
      'description' => 'CMS内容管理系统',
      'version' => 'v0.0.1',
      'develop' => false,
      'type' => 'module',
      'keywords' => 
      array (
        0 => 'cms',
        1 => '内容管理系统',
      ),
      'homepage' => 'https://www.pangtou.com',
      'docs' => 'https://www.pangtou.com',
      'authors' => 
      array (
        0 => 
        array (
          'name' => 'Zane',
          'email' => '873934580@qq.com',
        ),
      ),
      'providers' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestProviderServices',
      'compatibility' => 
      array (
        'ptadmin/admin' => '>=1.0',
        'ptadmin/base' => '>=1.0',
      ),
      'entry' => 
      array (
        'installer' => 'Addon\\Test\\Installer',
        'bootstrap' => 'Addon\\Test\\Bootstrap',
      ),
      'resources' => 
      array (
        'assets' => './Assets',
        'routes' => './Routes',
        'views' => './Response/views',
        'lang' => './Response/Lang',
        'config' => './Config',
        'functions' => './functions.php',
      ),
      'title' => '演示配置文件',
      'base_path' => 'Test',
      'response' => 
      array (
        'asset' => './Assets',
        'route' => './Routes',
        'view' => './Response/views',
        'lang' => './Response/Lang',
        'config' => './Config',
        'func' => './functions.php',
      ),
      'require' => 
      array (
      ),
    ),
    'providers' => 
    array (
      0 => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestProviderServices',
    ),
    'response' => 
    array (
      0 => false,
      1 => false,
      2 => false,
      3 => false,
      4 => false,
      5 => false,
    ),
    'inject' => 
    array (
      'payment' => 
      array (
        0 => 
        array (
          'code' => 'wechat_pay',
          'type' => 
          array (
            0 => 'jsapi',
            1 => 'qrcode',
          ),
          'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestPaymentService',
          'title' => '微信支付',
        ),
        1 => 
        array (
          'code' => 'alipay',
          'type' => 
          array (
            0 => 'web',
          ),
          'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestAlipayService',
          'title' => '支付宝',
        ),
      ),
      'auth' => 
      array (
        0 => 
        array (
          'code' => 'qq_login',
          'type' => 
          array (
            0 => 'pc',
            1 => 'mobile',
          ),
          'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestInjectServices',
          'title' => 'QQ登录',
        ),
      ),
      'notify' => 
      array (
        0 => 
        array (
          'code' => 'site_notify',
          'type' => 
          array (
            0 => 'site',
            1 => 'template',
          ),
          'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestInjectServices',
          'title' => '站内通知',
        ),
      ),
      'storage' => 
      array (
        0 => 
        array (
          'code' => 'oss_storage',
          'type' => 
          array (
            0 => 'oss',
            1 => 'private',
          ),
          'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestInjectServices',
          'title' => 'OSS 存储',
        ),
      ),
    ),
    'directives' => 
    array (
      'lists' => 
      array (
        'name' => 'lists',
        'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestDirectives',
        'method' => 'handle',
        'type' => 'loop',
        'cache' => true,
        'title' => '列表展示',
      ),
      'auth' => 
      array (
        'name' => 'auth',
        'class' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestDirectives',
        'method' => 'auth',
        'type' => 'if',
        'cache' => true,
        'title' => '是否访问',
      ),
    ),
    'hooks' => 
    array (
      'payment.success' => 
      array (
        0 => 
        array (
          'event' => 'payment.success',
          'handler' => 'PTAdmin\\AddonTests\\Feature\\Addon\\testSrc\\addons\\Test\\TestDirectives@paymentSuccess',
          'priority' => 10,
        ),
      ),
    ),
  ),
);