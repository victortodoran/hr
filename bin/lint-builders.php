<?php

/**
 * Fails when a public builder method doesn't apply its conditions through
 * scoped(): its body must start with `return $this->scoped(`. Query scopes
 * bracket their conditions when the query already has some; scoped() keeps
 * that, so an earlier orWhere can't swallow them. A method whose whole body
 * is one aggregate, such as `return $this->withCount([...]);`, adds no
 * conditions to the query and may skip it.
 */

require __DIR__.'/../vendor/autoload.php';

$files = glob(__DIR__.'/../app/Models/Builders/*Builder.php') ?: [];
$files = array_filter($files, fn (string $file): bool => basename($file) !== 'ModelBuilder.php');

if ($files === []) {
    fwrite(STDERR, "lint:builders found no builders in app/Models/Builders.\n");
    exit(2);
}

$unscoped = [];

/**
 * Whether the method body is nothing but `return $this->with<Aggregate>(...);`,
 * the aggregate's own closures aside: its call closes right before the
 * statement's semicolon, so no condition is chained after it.
 */
function isOnlyAnAggregate(string $body): bool
{
    if (preg_match('/^return \$this->with(Count|Sum|Avg|Min|Max|Exists|Aggregate)\(/', $body, $match) !== 1) {
        return false;
    }

    $depth = 0;

    for ($at = strlen($match[0]) - 1; $at < strlen($body); $at++) {
        $depth += match ($body[$at]) {
            '(' => 1,
            ')' => -1,
            default => 0,
        };

        if ($depth === 0) {
            return preg_match('/^\);\s*}\s*$/', substr($body, $at)) === 1;
        }
    }

    return false;
}

foreach ($files as $file) {
    $class = 'App\\Models\\Builders\\'.basename($file, '.php');
    $lines = file($file);

    foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $class || $method->isStatic()) {
            continue;
        }

        $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
        $firstStatement = trim(substr($body, strpos($body, '{') + 1));

        if (! str_starts_with($firstStatement, 'return $this->scoped(') && ! isOnlyAnAggregate($firstStatement)) {
            $unscoped[] = 'app/Models/Builders/'.basename($file).': '.$method->getName();
        }
    }
}

foreach ($unscoped as $method) {
    echo "Start the method with return \$this->scoped(...): {$method}\n";
}

exit($unscoped === [] ? 0 : 1);
