<?php
class Lexer {
    protected static $_terminals = array(
        "/^(\\\)/" => "T_ESCAPE",
        "/^(\")/" => "T_QUOTE",
        "/^(\s+)/" => "T_WHITESPACE",
        "/^(OR)/" => "T_OR",
        "/^(AND)/" => "T_AND",
        "/^(\()/" => "T_OPEN_PARENTHESIS",
        "/^(\))/" => "T_CLOSE_PARENTHESIS",
        "/^(~)/" => "T_TILDE",
        "/^([\p{L}\p{S}\p{N}]+)/u" => "T_WORD",
        "/^(\p{P})/u" => "T_PUNCTUATION",
    );

    public static function run($source) {
        $tokens = array();
        $previousOffset = "";
     
        foreach($source as $number => $line) {            
            $offset = 0;
            while($offset < strlen($line)) {
                $result = static::_match($line, $number, $offset);
                if($result === false) {
                    throw new Exception("Unable to parse line " . ($line+1) . ".");
                }
                $tokens[] = $result;
                $offset += strlen($result['match']);
                $previousOffset = $offset;
            }
        }
     
        return $tokens;
    }

    protected static function _match($line, $number, $offset) {
        $string = substr($line, $offset);
     
        foreach(static::$_terminals as $pattern => $name) {
            if(preg_match($pattern, $string, $matches)) {
                return array(
                    'match' => $matches[1],
                    'token' => $name,
                    'line' => $number+1
                );
            }
        }
     
        return false;
    }
}

$keys = array();

$input = array('"try me" ~~please!!§§ (I\'2m a good guy\)) OR (maybe not) AND "smart???" \AND');
$result = Lexer::run($input);

// Mode are word, partial, string, false
$currentMode = false;

$inEscape = false;
$currentWord = "";
$firstRun = true;

foreach ($result as $value) {
    if ($inEscape) {
        $currentWord .= $value["match"];
        $inEscape = false;
        continue;
    }
    if ($value["token"] === "T_ESCAPE") {
        $inEscape = true;
        continue;
    }
    if ($value["token"] === "T_QUOTE") {
        if ($currentMode === false) {
            $currentMode = "string";
            continue;
        } else if ($currentMode === "string") {
            $keys[] = array(
                "word" => $currentWord,
                "mode" => "string"
            );
            $currentWord = "";
            $currentMode = false;
        } else {
            $currentWord .= $value["match"];
        }
    }
    if ($currentMode === "string") {
        $currentWord .= $value["match"];
        continue;
    }
    if ($value["token"] === "T_WHITESPACE") {
        if ($currentWord !== "") {
            $keys[] = array(
                "word" => $currentWord,
                "mode" => $currentMode ? $currentMode : "word"
            );
        }
        $currentWord = "";
        $currentMode = false;
        continue;
    }
    if ($value["token"] === "T_TILDE") {
        if ($currentMode !== "partial") {
            $currentMode = "partial";
        } else {
            $currentWord .= $value["match"];
        }
        
    }
    if ($value["token"] === "T_OPEN_PARENTHESIS") {
        $keys[] = array("mode" => "open_parenthesis");
        continue;
    }
    if ($value["token"] === "T_CLOSE_PARENTHESIS") {
        $keys[] = array("mode" => "close_parenthesis");
        continue;
    }
    if ($value["token"] === "T_OR") {
        $keys[] = array("mode" => "or");
        continue;
    }
    if ($value["token"] === "T_AND") {
        $keys[] = array("mode" => "and");
        continue;
    }
    if ($value["token"] === "T_WORD") {
        $currentWord .= $value["match"];
    }
    if ($value["token"] === "T_PUNCTUATION") {
        $currentWord .= $value["match"];
    }
}
if ($currentWord !== "") {
    $keys[] = array(
        "word" => $currentWord,
        "mode" => $currentMode ? $currentMode : "word"
    );
}
var_dump($result);
var_dump($input);
var_dump($keys);
?>

