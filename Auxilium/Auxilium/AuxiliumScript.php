<?php

namespace Auxilium\Auxilium;

final class AuxiliumScript
{
    public static function evaluate_expression(string $string, array $vars)
    {
        $string = trim($string);

        // Handle empty string
        if($string === '')
        {
            return null;
        }

        $isDollarPrefixedFunctionCall = false;
        if(str_starts_with($string, "\$") && preg_match('/^\$[A-Za-z_][A-Za-z0-9_]*\s*\(/', $string))
        {
            $string = substr($string, 1);
            $isDollarPrefixedFunctionCall = true;
        }

        // Handle variables (only if NOT a $-prefixed function call)
        if(!$isDollarPrefixedFunctionCall && str_starts_with($string, "\$"))
        {
            return self::evaluate_variable_path($string, $vars);
        }
        // Handle string literals
        elseif(str_starts_with($string, "\""))
        {
            $id = 1;
            $output = "";
            while($id < strlen($string))
            {
                $contents = substr($string, $id, 1);
                if($contents === "\\")
                {
                    $id++;
                    $output .= substr($string, $id, 1);
                }
                elseif($contents === "\"")
                {
                    break;
                }
                else
                {
                    $output .= $contents;
                }
                $id++;
            }
            return $output;
        }
        // Handle function calls
        else
        {
            $id = strpos($string, "(");

            // If no parentheses found, treat as a simple value
            if($id === false)
            {
                // Check if it's a number
                if(is_numeric($string))
                {
                    return $string + 0; // Convert to int or float
                }
                // Check for boolean keywords without parentheses
                if(strtolower($string) === 'true')
                {
                    return true;
                }
                if(strtolower($string) === 'false')
                {
                    return false;
                }
                // Otherwise return as string
                return $string;
            }

            $fn = substr($string, 0, $id);

            // Handle case where function name is empty
            if($fn === '')
            {
                return null;
            }

            $bl = 1;
            $id++;
            $args = [];
            $arg = "";
            while(($bl > 0) && ($id < strlen($string)))
            {
                $contents = substr($string, $id, 1);
                if($contents === "(")
                {
                    $arg .= "(";
                    $bl++;
                }
                elseif($contents === ")")
                {
                    if($bl !== 1)
                    {
                        $arg .= ")";
                    }
                    $bl--;
                }
                elseif($contents === ",")
                {
                    if($bl === 1)
                    {
                        $args[] = trim($arg);
                        $arg = "";
                    }
                    else
                    {
                        $arg .= ",";
                    }
                }
                else
                {
                    $arg .= $contents;
                }
                $id++;
            }

            // Add the last argument if not empty
            $arg = trim($arg);
            if($arg !== '' || count($args) > 0)
            {
                $args[] = $arg;
            }

            switch(strtolower($fn))
            {
                case "true":
                    return true;
                case "false":
                    return false;
                case "not":
                    if(count($args) < 1)
                    {
                        return false;
                    }
                    return !self::evaluate_expression($args[0], $vars);
                case "exists":
                    if(count($args) < 1)
                    {
                        return false;
                    }
                    $expr = self::evaluate_expression($args[0], $vars);
                    return !($expr === null || $expr === '' || $expr === false);
                case "or":
                    if(count($args) < 1)
                    {
                        return false;
                    }
                    for($i = 0, $iMax = count($args); $i < $iMax; $i++)
                    {
                        if(self::evaluate_expression($args[$i], $vars))
                        {
                            return true;
                        }
                    }
                    return false;
                case "and":
                    if(count($args) < 1)
                    {
                        return true;
                    }
                    for($i = 0, $iMax = count($args); $i < $iMax; $i++)
                    {
                        if(!self::evaluate_expression($args[$i], $vars))
                        {
                            return false;
                        }
                    }
                    return true;
                case "eq":
                case "equals":
                    if(count($args) < 2)
                    {
                        return false;
                    }
                    $evali0 = self::evaluate_expression($args[0], $vars);
                    for($i = 1, $iMax = count($args); $i < $iMax; $i++)
                    {
                        $evalin = self::evaluate_expression($args[$i], $vars);
                        if($evali0 !== $evalin)
                        {
                            return false;
                        }
                    }
                    return true;
                case "concat":
                    $evald_args = [];
                    for($i = 0, $iMax = count($args); $i < $iMax; $i++)
                    {
                        $evald_args[] = self::evaluate_expression($args[$i], $vars);
                    }
                    return implode("", $evald_args);
                default:
                    // Unknown function - return null
                    return null;
            }
        }
    }

    public static function evaluate_variable_path(string $string, array $vars)
    {
        if(str_starts_with($string, "\$"))
        {
            // Handle array access like $formData["field_name"]
            if(preg_match('/^\$(\w+)\["([^"]+)"\]$/', $string, $matches))
            {
                $varName = $matches[1];
                $key = $matches[2];
                if(isset($vars[$varName]) && is_array($vars[$varName]))
                {
                    return $vars[$varName][$key] ?? null;
                }
                return null;
            }

            // Handle array access with single quotes like $formData['field_name']
            if(preg_match('/^\$(\w+)\[\'([^\']+)\'\]$/', $string, $matches))
            {
                $varName = $matches[1];
                $key = $matches[2];
                if(isset($vars[$varName]) && is_array($vars[$varName]))
                {
                    return $vars[$varName][$key] ?? null;
                }
                return null;
            }

            $varName = substr($string, 1);
            return $vars[$varName] ?? null;
        }

        if(str_starts_with($string, "\\\$"))
        {
            return substr($string, 1);
        }
        return $string;
    }
}
