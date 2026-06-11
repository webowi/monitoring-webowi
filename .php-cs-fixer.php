<?php

declare(strict_types=1);

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect(10, 300))
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@PSR12' => true,
        '@PSR12:risky' => true,
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
        'fully_qualified_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => false,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'modernize_strpos' => true,
        'modernize_types_casting' => true,
        'native_function_invocation' => true,
        'native_constant_invocation' => true,
        'no_unneeded_import_alias' => true,
        'no_unset_cast' => true,
        'no_unused_imports' => true,
        'no_useless_else' => true,
        'single_line_empty_body' => true,
        'single_line_throw' => false,
        'use_arrow_functions' => true,
    ])
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in(__DIR__)
            ->exclude([
                'var',
                'vendor',
                '.gitlab',
                'bin',
                'config',
                'docker',
                'fixtures',
                'public',
                'terraform',
                'translations',
            ])
            ->notName(['Kernel.php', 'bootstrap.php'])
    );
