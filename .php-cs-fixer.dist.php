<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'braces' => [
            'position_after_functions_and_oop_constructs' => 'same',
        ],
        'function_declaration' => [
            'closure_function_spacing' => 'none',
        ],
    ])
    ->setIndent("\t")
    ->setLineEnding("\n")
    ->setFinder($finder);
