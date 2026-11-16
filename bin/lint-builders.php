<?php

/**
 * Fails when a public builder method doesn't apply its conditions through
 * scoped(): its body must start with `return $this->scoped(`. Query scopes
 * bracket their conditions when the query already has some; scoped() keeps
 * that, so an earlier orWhere can't swallow them.
 */

require __DIR__.'/../vendor/autoload.php';

$files = glob(__DIR__.'/../app/Models/Builders/*Builder.php') ?: [];
$files = array_filter($files, fn (string $file): bool => basename($file) !== 'ModelBuilder.php');

if ($files === []) {
    fwrite(STDERR, "lint:builders found no builders in app/Models/Builders.\n");
    exit(2);
}

$unscoped = [];

foreach ($files as $file) {
    $class = 'App\\Models\\Builders\\'.basename($file, '.php');
    $lines = file($file);

    foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $class || $method->isStatic()) {
            continue;
        }

        $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
        $firstStatement = trim(substr($body, strpos($body, '{') + 1));

        if (! str_starts_with($firstStatement, 'return $this->scoped(')) {
            $unscoped[] = 'app/Models/Builders/'.basename($file).': '.$method->getName();
        }
    }
}

foreach ($unscoped as $method) {
    echo "Start the method with return \$this->scoped(...): {$method}\n";
}

exit($unscoped === [] ? 0 : 1);
