<?php
/**
 * PHP-CS-Fixer configuration.
 *
 * Requires friendsofphp/php-cs-fixer
 */
$config = PhpCsFixer\Config::create();
$config->setRules([
  '@Symfony' => true,
  'array_syntax' => ['syntax' => 'short'],
  'concat_space' => ['spacing' => 'one'],
  'phpdoc_var_without_name' => false,
  'align_multiline_comment' => true,
  'simplified_null_return' => false,
]);
$config->setRiskyAllowed(false);

return $config;
