<?php

require_once __DIR__ . "/../vendor/autoload.php";

$ion = new \ReflectionExtension('ion');
echo $ion->info() . PHP_EOL;

echo PHP_EOL;

foreach($ion->getINIEntries() as $ini => $value)
{
    echo  "ini $ini = ".var_export($value, true) . PHP_EOL;
}

echo PHP_EOL;

foreach($ion->getConstants() as $constant => $value)
{
    echo "const $constant = ".var_export($value, true) . PHP_EOL;
}

echo PHP_EOL;

foreach($ion->getFunctions() as $function)
{
    /** @var ReflectionFunction $function */
    echo scanFunction($function) . PHP_EOL;
}

echo PHP_EOL;

foreach($ion->getClasses() as $class)
{
    $mods = [];
    /** @var ReflectionClass $class */
    if($class->isFinal()) {
        $mods[] = "final";
    }
    if($class->isInterface()) {
        $mods[] = "interface";
    } elseif($class->isTrait()) {
        $mods[] = "trait";
    } else {
        if($class->isAbstract()) {
            $mods[] = "abstract";
        }
        $mods[] = "class";
    }

    echo implode(' ', $mods)." {$class->name}" . PHP_EOL;
    if($class->getParentClass()) {
        echo "  extends {$class->getParentClass()->name}" . PHP_EOL;
    }
    foreach($class->getInterfaceNames() as $interface) {
        echo "  implements {$interface}" . PHP_EOL;
    }
    foreach($class->getTraitNames() as $trait) {
        echo "  use {$trait}" . PHP_EOL;
    }
    foreach($class->getConstants() as $constant => $value) {
        echo "  const {$class->name}::{$constant} = ".var_export($value, true) . PHP_EOL;
    }
    foreach($class->getProperties() as $prop_name => $prop) {
        /** @var ReflectionProperty $prop */
        $mods = implode(' ', Reflection::getModifierNames($prop->getModifiers()));
        if($prop->class !== $class->name) {
            echo "  prop $mods {$prop->class}::\${$prop->name}" . PHP_EOL;
        } else {
            echo "  prop $mods \${$prop->name}" . PHP_EOL;
        }

    }
    foreach($class->getMethods() as $method) {
        echo "  " . scanFunction($method, $class->name) . PHP_EOL;
    }
    echo PHP_EOL;

}


/**
 * @param ReflectionFunctionAbstract $function
 * @param string $class_name
 * @return string
 */
function scanFunction(ReflectionFunctionAbstract $function, $class_name = "") {
    $params = [];
    foreach($function->getParameters() as $param) {
        /* @var ReflectionParameter $param */
        $type = "";
        $param_name = "$".$param->name;
        if($param->getClass()) {
            $type = $param->getClass()->name;
        } elseif ($param->hasType()) {
            $type = $param->getType();
        } elseif ($param->isArray()) {
            $type = "Array";
        } elseif ($param->isCallable()) {
            $type = "callable";
        }
        if($param->isVariadic()) {
            $param_name = "...".$param_name;
        }
        if($type) {
            $param_name = $type." ".$param_name;
        }
        if($param->isOptional()) {
            $params[] = "[ ".$param_name." ]";
        } else {
            $params[] = $param_name;
        }
    }
    if($function->hasReturnType()) {
        $return = " : ".$function->getReturnType();
    } else {
        $return = "";
    }
    $declare = $function->name;
    if($function instanceof ReflectionFunction) {
        $declare = "function {$function->name}";
    } elseif ($function instanceof ReflectionMethod) {
        $mods =  implode(' ', Reflection::getModifierNames($function->getModifiers()));
        if($function->class !== $class_name) {
            $declare = "method {$mods} {$function->class}::{$function->name}";
        } else {
            $declare = "method {$mods} {$function->name}";
        }

    }
    return "{$declare}(".implode(", ", $params).")$return";
}