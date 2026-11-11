<?php
return [
 'default'=>env('LOG_CHANNEL','single'), 'deprecations'=>['channel'=>env('LOG_DEPRECATIONS_CHANNEL','null'),'trace'=>false],
 'channels'=>[
  'single'=>['driver'=>'single','path'=>storage_path('logs/laravel.log'),'level'=>env('LOG_LEVEL','warning'),'replace_placeholders'=>true],
  'stderr'=>['driver'=>'monolog','level'=>env('LOG_LEVEL','warning'),'handler'=>Monolog\Handler\StreamHandler::class,'with'=>['stream'=>'php://stderr']],
  'null'=>['driver'=>'monolog','handler'=>Monolog\Handler\NullHandler::class],
 ],
];
