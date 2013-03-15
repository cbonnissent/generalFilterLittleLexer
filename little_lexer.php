<?php
namespace Dcp\Lex;

/**
 * Analyse a general filter string
 * @package Dcp\Lex
 */
class GeneralFilter
{
    const T_ESCAPE = "T_ESCAPE";
    const T_QUOTE = "T_QUOTE";
    const T_WHITESPACE = "T_WHITESPACE";
    const T_TILDE = "T_TILDE";
    const T_OPEN_PARENTHESIS = "T_OPEN_PARENTHESIS";
    const T_CLOSE_PARENTHESIS = "T_CLOSE_PARENTHESIS";
    const T_OR = "T_OR";
    const T_AND = "T_AND";
    const T_WORD = "T_WORD";
    const T_PUNCTUATION = "T_PUNCTUATION";
    const MODE_STRING = "string";
    const MODE_WORD = "word";
    const MODE_OPEN_PARENTHESIS = "open_parenthesis";
    const MODE_PARTIAL = "partial";
    const MODE_CLOSE_PARENTHESIS = "close_parenthesis";
    const MODE_OR = "or";
    const MODE_AND = "and";

    /**
     * List of tokens in priority order
     * @var array
     */
    protected static $_terminals = array(
        '/^(\\\)/' => self::T_ESCAPE,
        '/^(\")/' => self::T_QUOTE,
        '/^(\s+)/' => self::T_WHITESPACE,
        '/^(OR)/' => self::T_OR,
        '/^(AND)/' => self::T_AND,
        '/^(\()/' => self::T_OPEN_PARENTHESIS,
        '/^(\))/' => self::T_CLOSE_PARENTHESIS,
        '/^(~)/' => self::T_TILDE,
        '/^([\p{L}\p{S}\p{N}]+)/u' => self::T_WORD,
        '/^(\p{P})/u' => self::T_PUNCTUATION,
    );

    /**
     * Analyze a general filter string
     *
     * @param string $source the filter
     * @param bool $onlyToken use it if you only want the lexer token
     *
     * @return array
     *  array of filter elements ("mode" => word, string, partial, open_parenthesis, close_parenthesis, and, or, "word" => currentWord)
     *  or array of token elements ("token" => token type (see $_terminals), "match" => matched string)
     *
     * @throws LexException
     */
    public static function analyze($source, $onlyToken = false)
    {
        $tokens = array();
        $offset = 0;
        while ($offset < strlen($source)) {
            $result = static::_match($source, $offset);
            if ($result === false) {
                throw new LexException(sprintf(_("LEX_GENERAL_FILTER:Unable to parse %s"), $source));
            }
            $tokens[] = $result;
            $offset += strlen($result['match']);
        }
        if ($onlyToken) {
            return $tokens;
        }else {
            return static::_convertToken($tokens);
        }
    }

    /**
     * Analyze a fragment of source
     *
     * @param string $line current line
     * @param int $offset offset of the line
     * @return array|bool current fragment or false
     */
    protected static function _match($line, $offset)
    {
        $string = substr($line, $offset);

        foreach (static::$_terminals as $pattern => $name) {
            if (preg_match($pattern, $string, $matches)) {
                return array(
                    'match' => $matches[1],
                    'token' => $name
                );
            }
        }
        return false;
    }

    /**
     * Convert the tokens in filter element
     *
     * @param $tokens array of token
     * @return array array of filter elements
     */
    protected static function _convertToken($tokens)
    {
        // Keys are stored in this array
        $keys = array();
        // Mode are word, partial, string, false
        $currentMode = false;

        $inEscape = false;
        $currentWord = "";

        foreach ($tokens as $value) {
            if ($inEscape) {
                $currentWord .= $value["match"];
                $inEscape = false;
                continue;
            }
            if ($value["token"] === self::T_ESCAPE) {
                $inEscape = true;
                continue;
            }
            if ($value["token"] === self::T_QUOTE) {
                if ($currentMode === false) {
                    $currentMode = self::MODE_STRING;
                    continue;
                } else if ($currentMode === self::MODE_STRING) {
                    $keys[] = array(
                        "word" => $currentWord,
                        "mode" => self::MODE_STRING
                    );
                    $currentWord = "";
                    $currentMode = false;
                } else {
                    $currentWord .= $value["match"];
                }
            }
            if ($currentMode === self::MODE_STRING) {
                $currentWord .= $value["match"];
                continue;
            }
            if ($value["token"] === self::T_WHITESPACE) {
                if ($currentWord !== "") {
                    $keys[] = array(
                        "word" => $currentWord,
                        "mode" => $currentMode ? $currentMode : self::MODE_WORD
                    );
                }
                $currentWord = "";
                $currentMode = false;
                continue;
            }
            if ($value["token"] === self::T_TILDE) {
                if ($currentMode !== self::MODE_PARTIAL) {
                    $currentMode = self::MODE_PARTIAL;
                } else {
                    $currentWord .= $value["match"];
                }
            }
            if ($value["token"] === self::T_OPEN_PARENTHESIS) {
                $keys[] = array("mode" => self::MODE_OPEN_PARENTHESIS);
                continue;
            }
            if ($value["token"] === self::T_CLOSE_PARENTHESIS) {
                if ($currentWord !== "") {
                    $keys[] = array(
                        "word" => $currentWord,
                        "mode" => $currentMode ? $currentMode : self::MODE_WORD
                    );
                }
                $currentWord = "";
                $keys[] = array("mode" => self::MODE_CLOSE_PARENTHESIS);
                continue;
            }
            if ($value["token"] === self::T_OR) {
                $keys[] = array("mode" => self::MODE_OR);
                continue;
            }
            if ($value["token"] === self::T_AND) {
                $keys[] = array("mode" => self::MODE_AND);
                continue;
            }
            if ($value["token"] === self::T_WORD) {
                $currentWord .= $value["match"];
            }
            if ($value["token"] === self::T_PUNCTUATION) {
                $currentWord .= $value["match"];
            }
        }
        if ($currentWord !== "") {
            $keys[] = array(
                "word" => $currentWord,
                "mode" => $currentMode ? $currentMode : self::MODE_WORD
            );
        }
        return $keys;
    }
}

class LexException extends \Exception
{

}

var_dump(GeneralFilter::analyze('je suis "Dynacase Platform" un ~fram!!'));