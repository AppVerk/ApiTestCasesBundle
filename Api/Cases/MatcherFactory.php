<?php

namespace AppVerk\ApiTestCasesBundle\Api\Cases;

use Coduo\PHPMatcher\Lexer;
use Coduo\PHPMatcher\Matcher;
use Coduo\PHPMatcher\Parser;

class MatcherFactory
{
    /**
     * @return Matcher
     */
    public static function buildXmlMatcher()
    {
        return self::buildMatcher(Matcher\XmlMatcher::class);
    }

    /**
     * @param string $matcherClass
     *
     * @return Matcher
     */
    protected static function buildMatcher($matcherClass)
    {
        $orMatcher = self::buildOrMatcher();
        $chainMatcher = new Matcher\ChainMatcher(
            [
                new $matcherClass($orMatcher),
            ]
        );

        return new Matcher($chainMatcher);
    }

    /**
     * @return Matcher\ChainMatcher
     */
    protected static function buildOrMatcher()
    {
        $scalarMatchers = self::buildScalarMatchers();
        $orMatcher = new Matcher\OrMatcher($scalarMatchers);
        $arrayMatcher = new Matcher\ArrayMatcher(
            new Matcher\ChainMatcher(
                [
                    $orMatcher,
                    $scalarMatchers,
                ]
            ),
            self::buildParser()
        );
        $chainMatcher = new Matcher\ChainMatcher(
            [
                $orMatcher,
                $arrayMatcher,
            ]
        );

        return $chainMatcher;
    }

    /**
     * @return Matcher\ChainMatcher
     */
    protected static function buildScalarMatchers()
    {
        $parser = self::buildParser();

        return new Matcher\ChainMatcher(
            [
                new Matcher\CallbackMatcher(),
                new Matcher\ExpressionMatcher(),
                new Matcher\NullMatcher(),
                new Matcher\StringMatcher($parser),
                new Matcher\IntegerMatcher($parser),
                new Matcher\BooleanMatcher(),
                new Matcher\DoubleMatcher($parser),
                new Matcher\NumberMatcher(),
                new Matcher\ScalarMatcher(),
                new Matcher\WildcardMatcher(),
            ]
        );
    }

    /**
     * @return Parser
     */
    protected static function buildParser()
    {
        return new Parser(new Lexer(), new Parser\ExpanderInitializer());
    }

    /**
     * @return Matcher
     */
    public static function buildJsonMatcher()
    {
        return self::buildMatcher(Matcher\JsonMatcher::class);
    }
}