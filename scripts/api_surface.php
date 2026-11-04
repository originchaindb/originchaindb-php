<?php

declare(strict_types=1);
$root = $argv[1] ?? dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'OriginChainDB\\')) {
        require $root . '/src/' . substr($class, 14) . '.php';
    }
});
$surface = [];
foreach (glob($root . '/src/*.php') as $file) {
    $name = 'OriginChainDB\\' . basename($file, '.php');
    if (!class_exists($name) && !trait_exists($name)) {
        continue;
    }
    $class = new ReflectionClass($name);
    $surface[$name] = ($class->isFinal() ? 'final ' : '') . ($class->isTrait() ? 'trait' : 'class') . ' extends ' . ($class->getParentClass() ? $class->getParentClass()->getName() : '');
    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $name) {
            continue;
        }
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $parameters[] = ($parameter->isPassedByReference() ? '&' : '') . ($parameter->isVariadic() ? '...' : '') . $parameter->getName() . ':' . (string)$parameter->getType() . ($parameter->isOptional() ? '?' : '!');
        }
        $surface[$name . '::' . $method->getName()] = ($method->isStatic() ? 'static ' : '') . '(' . implode(',', $parameters) . '):' . (string)$method->getReturnType();
    }
    foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        if ($property->getDeclaringClass()->getName() === $name) {
            $surface[$name . '::$' . $property->getName()] = ($property->isReadOnly() ? 'readonly ' : '') . (string)$property->getType();
        }
    }
}
ksort($surface);
echo json_encode($surface, JSON_THROW_ON_ERROR);
