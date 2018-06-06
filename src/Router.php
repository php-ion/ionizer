<?php
/**
 *
 */

namespace Ionizer;


use Ionizer\Actor\Options\OptionsInterface;
use Koda\ClassInfo;
use Koda\Error\InvalidArgumentException;
use Koda\Handler;

class Router
{

    public $ionizer;
    public $info;

    /**
     * Router constructor.
     * @param Ionizer $ionizer
     */
    public function __construct(Ionizer $ionizer)
    {
        $this->ionizer = $ionizer;
    }

    public function run(array &$argv, string $controller_class)
    {
        $command = CLI::popArgument($argv);
        $controller = new $controller_class($this->ionizer);
        /** @var Controller $controller */
        $info = $controller->info;
        $handler = new Handler();
        try {
            if ($command) {
                if ($info->hasMethod("{$command}Command")) {
                    $method = $info->getMethod("{$command}Command");
                    $method_args = [];
                    foreach ($method->args as $arg) {
                        if ($arg->variadic) {
                            while ($method_args[] = array_shift($argv));
                        } elseif (is_subclass_of($arg->class_hint, OptionsInterface::class)) {
                            $options = $method_args[] = new $arg->class_hint;
                            foreach(CLI::popOptions($argv) as $k => $v) {
                                $m = "set" . str_replace("_", "", $k);
                                if (method_exists($options, $m)) {
                                    $options->{$m}($v);
                                } else {
                                    $options->{$k} = $v;
                                }
                            }
                        } else {
                            $arg = CLI::popArgument($argv);
                            if ($arg) {
                                $method_args[] = $arg;
                            }
                        }
                    }
                    $controller->info->getMethod("{$command}Command")->invoke($method_args, $handler->setContext($controller));
                } elseif (file_exists($command)) {
                    $controller->runCommand($command, ...array_values($argv));
                } else {
                    throw new \InvalidArgumentException("Command '{$command}' not found");
                }
            }  else {
                $controller->helpCommand();
            }
//        } catch (InvalidArgumentException $e) {
//            $this->ionizer->log->error("Required argument '" . $e->argument->name . "' (see: ion help {$command})");
        } catch (\Throwable $e) {
            $this->ionizer->log->error($e);
        }
    }
}